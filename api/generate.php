<?php
/**
 * Ollama Manager - Generate API Endpoint
 *
 * Handles single prompt completions using SQLite database.
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
$prompt = $input['prompt'] ?? '';
$options = $input['options'] ?? [];

if (empty($model)) {
    errorResponse('Model is required', 400);
}

if (empty($prompt)) {
    errorResponse('Prompt is required', 400);
}

$db = new Database();

// Get server settings
$settings = $db->getAllSettings();
$host = $settings['ollamaHost'] ?? OLLAMA_HOST;
$port = $settings['ollamaPort'] ?? OLLAMA_PORT;

$ollama = new OllamaAPI($host, $port);

$startTime = microtime(true);
$result = $ollama->generate($model, $prompt, $options);
$duration = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds

// Log API call
$db->addApiLog('/api/generate', 'POST', $input, $result, $duration);

if ($result['success']) {
    successResponse([
        'response' => $result['data']['response'] ?? '',
        'model' => $result['data']['model'] ?? $model,
        'created_at' => $result['data']['created_at'] ?? null,
        'done' => $result['data']['done'] ?? true,
        'context' => $result['data']['context'] ?? null,
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
