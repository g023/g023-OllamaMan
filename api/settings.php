<?php
/**
 * Ollama Manager - Settings API Endpoint
 *
 * Handles application settings using SQLite database.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = new Database();
$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        $settings = $db->getAllSettings();

        // Merge with defaults if some settings are missing
        global $defaultSettings;
        $settings = array_merge($defaultSettings, $settings);

        successResponse($settings);
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            errorResponse('Invalid JSON input', 400);
        }

        // Validate and sanitize settings
        $allowedKeys = [
            'theme', 'defaultModel', 'streamingEnabled',
            'maxHistoryItems', 'showNotifications',
            'autoRefreshInterval', 'temperature', 'maxTokens',
            'ollamaHost', 'ollamaPort'
        ];

        $validatedSettings = [];
        foreach ($allowedKeys as $key) {
            if (isset($input[$key])) {
                $validatedSettings[$key] = $input[$key];
            }
        }

        // Validate specific values
        if (isset($validatedSettings['theme']) && !in_array($validatedSettings['theme'], ['aqua', 'dark'])) {
            $validatedSettings['theme'] = 'aqua';
        }

        if (isset($validatedSettings['temperature'])) {
            $validatedSettings['temperature'] = max(0, min(2, (float)$validatedSettings['temperature']));
        }

        if (isset($validatedSettings['maxTokens'])) {
            $validatedSettings['maxTokens'] = max(1, min(128000, (int)$validatedSettings['maxTokens']));
        }

        if (isset($validatedSettings['maxHistoryItems'])) {
            $validatedSettings['maxHistoryItems'] = max(0, min(1000, (int)$validatedSettings['maxHistoryItems']));
        }

        if (isset($validatedSettings['autoRefreshInterval'])) {
            $validatedSettings['autoRefreshInterval'] = max(5, min(300, (int)$validatedSettings['autoRefreshInterval']));
        }

        // Validate Ollama host and port
        if (isset($validatedSettings['ollamaHost'])) {
            // Basic IP/hostname validation
            if (!preg_match('/^[a-zA-Z0-9.-]+$/', $validatedSettings['ollamaHost'])) {
                $validatedSettings['ollamaHost'] = '192.168.1.134'; // Default
            }
        }

        if (isset($validatedSettings['ollamaPort'])) {
            $port = (int)$validatedSettings['ollamaPort'];
            if ($port < 1 || $port > 65535) {
                $validatedSettings['ollamaPort'] = '11434'; // Default
            } else {
                $validatedSettings['ollamaPort'] = (string)$port;
            }
        }

        if ($db->setSettings($validatedSettings)) {
            successResponse([
                'message' => 'Settings saved successfully',
                'settings' => $validatedSettings
            ]);
        } else {
            errorResponse('Failed to save settings');
        }
        break;

    case 'reset':
        global $defaultSettings;

        if ($db->setSettings($defaultSettings)) {
            successResponse([
                'message' => 'Settings reset to defaults',
                'settings' => $defaultSettings
            ]);
        } else {
            errorResponse('Failed to reset settings');
        }
        break;

    default:
        errorResponse('Invalid action', 400);
}
