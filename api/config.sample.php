<?php

/**
 * API configuration.
 *
 * Holds secret API credentials. Do not expose this file:
 * - The .htaccess in this directory answers requests for config.php with a 404.
 * - This file outputs nothing when executed directly.
 * - .gitignore excludes it from version control.
 *
 * Replace the placeholders below with your real values.
 */

return [
    // DeepSeek (https://platform.deepseek.com/api_keys)
    'deepseek_api_key' => '',

    // Base URL of your Nextcloud (https://nextcloud.example.com)
    'nextcloud_base_url' => '',
    // Login name of the Nextcloud user that owns the Deck board
    'nextcloud_user' => '',
    // App password (Security → Devices & sessions → Create new app password)
    'nextcloud_app_password' => '',
    // Deck board (/apps/deck/#/board/42 ⇒ 42)
    'deck_board_id' => '',
    // Only cards carrying this tag are viewable
    'deck_public_tag' => 'Public [👁️]',
];
