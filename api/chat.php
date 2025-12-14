<?php
/**
 * Ollama Manager - Chat API Endpoint
 *
 * Handles chat conversations with models using SQLite database.
 */

require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/database.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('Invalid JSON input', 400);
}

$model = $input['model'] ?? '';
$messages = $input['messages'] ?? [];
$options = $input['options'] ?? [];

if (empty($model)) {
    errorResponse('Model is required', 400);
}

if (empty($messages)) {
    errorResponse('Messages array is required', 400);
}

// Validate messages format
foreach ($messages as $msg) {
    if (!isset($msg['role']) || !isset($msg['content'])) {
        errorResponse('Each message must have role and content', 400);
    }

    if (!in_array($msg['role'], ['system', 'user', 'assistant'])) {
        errorResponse('Invalid role. Must be system, user, or assistant', 400);
    }
}

$db = new Database();

// Get server settings
$settings = $db->getAllSettings();
$host = $settings['ollamaHost'] ?? OLLAMA_HOST;
$port = $settings['ollamaPort'] ?? OLLAMA_PORT;

$ollama = new OllamaAPI($host, $port);

$startTime = microtime(true);
$result = $ollama->chat($model, $messages, $options);
$duration = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds

// Log API call
$db->addApiLog('/api/chat', 'POST', $input, $result, $duration);

if ($result['success']) {
    $responseMessage = $result['data']['message'] ?? [];

    // Save to history if enabled
    $settings = $db->getAllSettings();
    if (($settings['maxHistoryItems'] ?? 100) > 0) {
        saveToHistory($db, $model, $messages, $responseMessage);
    }

    successResponse([
        'message' => $responseMessage,
        'model' => $result['data']['model'] ?? $model,
        'created_at' => $result['data']['created_at'] ?? null,
        'done' => $result['data']['done'] ?? true,
        'total_duration' => $result['data']['total_duration'] ?? null,
        'load_duration' => $result['data']['load_duration'] ?? null,
        'prompt_eval_count' => $result['data']['prompt_eval_count'] ?? null,
        'prompt_eval_duration' => $result['data']['prompt_eval_duration'] ?? null,
        'eval_count' => $result['data']['eval_count'] ?? null,
        'eval_duration' => $result['data']['eval_duration'] ?? null,
        'duration' => $duration
    ]);
} else {
    errorResponse($result['error']);
}

/**
 * Save conversation to history
 */
function saveToHistory($db, $model, $messages, $response) {
    // Add the assistant's response to messages for storage
    $allMessages = $messages;
    if ($response) {
        $allMessages[] = $response;
    }

    $db->addChatHistory($model, $allMessages);
}
