<?php

/**
 * DeepSeek account balance wrapper.
 *
 * GET /api/deepseek-balance.php
 *
 * Calls https://api.deepseek.com/user/balance with the configured API key
 * and passes the upstream JSON response through verbatim.
 */

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$apiKey = $config['deepseek_api_key'] ?? '';
if ($apiKey === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DeepSeek API key is not configured in config.php']);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'cURL extension is not available on this server']);
    exit;
}

$ch = curl_init('https://api.deepseek.com/user/balance');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
]);

$body = curl_exec($ch);

if ($body === false) {
    curl_close($ch);
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to reach the DeepSeek API']);
    exit;
}

$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

http_response_code($status);
header('Content-Type: application/json');
echo $body;
