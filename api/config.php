<?php
/**
 * Ollama Manager - Configuration
 * 
 * Central configuration for the Ollama Manager application.
 * This file handles server settings, timeouts, and application preferences.
 */

// Prevent direct access
if (!defined('OLLAMA_MANAGER')) {
    define('OLLAMA_MANAGER', true);
}

// Ollama Server Configuration
define('OLLAMA_HOST', '192.168.1.134');
define('OLLAMA_PORT', '11434');
define('OLLAMA_BASE_URL', 'http://' . OLLAMA_HOST . ':' . OLLAMA_PORT);

// API Endpoints
define('OLLAMA_API_GENERATE', OLLAMA_BASE_URL . '/api/generate');
define('OLLAMA_API_CHAT', OLLAMA_BASE_URL . '/api/chat');
define('OLLAMA_API_TAGS', OLLAMA_BASE_URL . '/api/tags');
define('OLLAMA_API_PS', OLLAMA_BASE_URL . '/api/ps');
define('OLLAMA_API_SHOW', OLLAMA_BASE_URL . '/api/show');
define('OLLAMA_API_PULL', OLLAMA_BASE_URL . '/api/pull');
define('OLLAMA_API_COPY', OLLAMA_BASE_URL . '/api/copy');
define('OLLAMA_API_DELETE', OLLAMA_BASE_URL . '/api/delete');
define('OLLAMA_API_EMBED', OLLAMA_BASE_URL . '/api/embed');
define('OLLAMA_API_CREATE', OLLAMA_BASE_URL . '/api/create');
define('OLLAMA_API_VERSION', OLLAMA_BASE_URL . '/api/version');

// Timeout Settings (in seconds)
define('OLLAMA_TIMEOUT_CONNECT', 5);
define('OLLAMA_TIMEOUT_EXECUTE', 1800); // 30 minutes for long generations
define('OLLAMA_TIMEOUT_PULL', 7200); // 2 hour for model downloads

// Application Settings
define('APP_NAME', 'Ollama Manager');
define('APP_VERSION', '1.0.0');
define('APP_DATA_DIR', __DIR__ . '/../data/');

// Database Configuration
define('DATABASE_PATH', APP_DATA_DIR . 'ollama_manager.db');

// Ensure data directory exists
if (!is_dir(APP_DATA_DIR)) {
    mkdir(APP_DATA_DIR, 0755, true);
}

// Legacy JSON file paths (for migration)
define('SETTINGS_FILE', APP_DATA_DIR . 'settings.json');
define('HISTORY_FILE', APP_DATA_DIR . 'chat_history.json');
define('FAVORITES_FILE', APP_DATA_DIR . 'favorites.json');
define('LOGS_FILE', APP_DATA_DIR . 'api_logs.json');

// Default Settings
$defaultSettings = [
    'theme' => 'aqua', // aqua or dark
    'defaultModel' => '',
    'streamingEnabled' => true,
    'maxHistoryItems' => 100,
    'showNotifications' => true,
    'autoRefreshInterval' => 30, // seconds
    'temperature' => 0.7,
    'maxTokens' => 2048
];

// CORS headers for API endpoints
function setCorsHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

// Error response helper
function errorResponse($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// Success response helper
function successResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}
