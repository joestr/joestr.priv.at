<?php

/**
 * Nextcloud Deck "public tasks" wrapper.
 *
 * GET /api/kanbantasks.php
 *
 * Reads one Nextcloud Deck board via the Deck API and returns only the cards
 * carrying the tag configured as `deck_public_tag` in config.php, grouped by
 * stack. All other cards are dropped here, server-side, so private tasks never
 * leave this endpoint.
 *
 * The Nextcloud credentials stay in config.php and are never exposed.
 * Responses are cached for CACHE_TTL seconds to avoid hammering Nextcloud
 * on every page view.
 */

declare(strict_types=1);

const CACHE_TTL = 60; // seconds

/** Emit a JSON error with the given HTTP status and stop. */
function fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** GET an OCS endpoint and return its decoded `ocs.data` payload. */
function deck_get(string $baseUrl, string $user, string $password, string $path): array
{
    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['OCS-APIRequest: true', 'Accept: application/json'],
        CURLOPT_USERPWD => $user . ':' . $password,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Failed to reach Nextcloud: ' . $error);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !isset($decoded['ocs']['data'])) {
        throw new RuntimeException('Unexpected Nextcloud response (HTTP ' . $status . ')');
    }

    $metaStatus = (int) ($decoded['ocs']['meta']['statuscode'] ?? 0);
    if ($status !== 200 || $metaStatus !== 200) {
        throw new RuntimeException(
            'Nextcloud API error: HTTP ' . $status . ', OCS ' . $metaStatus . ' for GET ' . $path
        );
    }

    return $decoded['ocs']['data'];
}

/** True when one of the labels carries the public tag (trimmed, case-insensitive). */
function has_public_tag(array $labels, string $publicTag): bool
{
    foreach ($labels as $label) {
        if (is_array($label) && strcasecmp(trim((string) ($label['title'] ?? '')), $publicTag) === 0) {
            return true;
        }
    }
    return false;
}

/** Map a raw Deck card to the slim public representation, or null if it must be filtered out. */
function map_public_card(array $card, string $publicTag): ?array
{
    if (!empty($card['archived'])) {
        return null;
    }

    $labels = $card['labels'] ?? [];
    if (!is_array($labels) || !has_public_tag($labels, $publicTag)) {
        return null;
    }

    return [
        'id' => (int) $card['id'],
        'title' => (string) ($card['title'] ?? ''),
        'description' => (string) ($card['description'] ?? ''),
        'duedate' => $card['duedate'] ?? null,
        'order' => (int) ($card['order'] ?? 0),
        // Keep the other labels (the public tag itself is redundant on the page).
        'labels' => array_values(array_filter($labels, static function ($label) use ($publicTag): bool {
            return is_array($label) && strcasecmp(trim((string) ($label['title'] ?? '')), $publicTag) !== 0;
        })),
    ];
}

$config = require __DIR__ . '/config.php';

$baseUrl = rtrim((string) ($config['nextcloud_base_url'] ?? ''), '/');
$user = (string) ($config['nextcloud_user'] ?? '');
$password = (string) ($config['nextcloud_app_password'] ?? '');
$boardId = (string) ($config['deck_board_id'] ?? '');
$publicTag = trim((string) ($config['deck_public_tag'] ?? 'Public [👁️]'));

if ($baseUrl === '' || $user === '' || $password === '') {
    fail(500, 'Nextcloud credentials are not configured in config.php');
}
if ($boardId === '') {
    fail(500, 'deck_board_id is not configured in config.php');
}
if ($publicTag === '') {
    fail(500, 'deck_public_tag is configured as empty in config.php');
}
if (!function_exists('curl_init')) {
    fail(500, 'cURL extension is not available on this server');
}

header('Content-Type: application/json');

// Serve a fresh cache entry without touching Nextcloud.
$cacheFile = sys_get_temp_dir() . '/kanbantasks-' . hash('sha256', $baseUrl . '|' . $boardId . '|' . $publicTag) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    echo file_get_contents($cacheFile);
    exit;
}

$apiBase = '/ocs/v2.php/apps/deck/api/v1.0/';

try {
    // Deck >= 1.13 returns the stacks of a board with their cards embedded.
    $stacks = deck_get(
        $baseUrl,
        $user,
        $password,
        $apiBase . 'stacks/' . rawurlencode($boardId)
    );
} catch (RuntimeException $e) {
    if (strpos($e->getMessage(), 'HTTP 404') === false) {
        fail(502, $e->getMessage());
    }
    // A 404 means either a wrong board id or an unreachable Deck API.
    // Probe the board list to tell the two apart.
    try {
        deck_get($baseUrl, $user, $password, $apiBase . 'boards');
        fail(502, 'Deck board "' . $boardId . '" was not found on ' . $baseUrl
            . '. Check deck_board_id in config.php.');
    } catch (RuntimeException $probeError) {
        fail(502, 'Deck API is not reachable: ' . $probeError->getMessage()
            . '. Is the Deck app installed and enabled?');
    }
}

$result = [
    'board' => $boardId,
    'generated_at' => gmdate('c'),
    'stacks' => [],
];

foreach ($stacks as $stack) {
    if (!is_array($stack)) {
        continue;
    }

    $stackId = (int) $stack['id'];
    // Cards arrive embedded in the stacks response.
    $cards = is_array($stack['cards'] ?? null) ? $stack['cards'] : [];

    $publicCards = [];
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $mapped = map_public_card($card, $publicTag);
        if ($mapped !== null) {
            $publicCards[] = $mapped;
        }
    }

    usort($publicCards, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

    $result['stacks'][] = [
        'id' => $stackId,
        'title' => (string) ($stack['title'] ?? ''),
        'order' => (int) ($stack['order'] ?? 0),
        'cards' => $publicCards,
    ];
}

usort($result['stacks'], static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

$json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fail(500, 'Failed to encode the response');
}

file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
