<?php
/**
 * Ollama Manager - Streaming Chat API Endpoint
 *
 * Handles real-time streaming chat responses using Server-Sent Events (SSE).
 * This provides token-by-token response streaming for a better UX.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Get server settings
$db = new Database();
$settings = $db->getAllSettings();
$host = $settings['ollamaHost'] ?? OLLAMA_HOST;
$port = $settings['ollamaPort'] ?? OLLAMA_PORT;
$baseUrl = 'http://' . $host . ':' . $port;

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Set time limit for long responses
set_time_limit(300);

// Helper to send SSE event
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Helper to send error and exit
function sendError($message) {
    sendSSE('error', ['error' => $message]);
    sendSSE('done', ['done' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendError('Invalid JSON input');
}

$model = $input['model'] ?? '';
$messages = $input['messages'] ?? [];
$options = $input['options'] ?? [];
$systemPrompt = $input['system'] ?? null;
$images = $input['images'] ?? [];
$tools = $input['tools'] ?? [];
$format = $input['format'] ?? null;

if (empty($model)) {
    sendError('Model is required');
}

if (empty($messages)) {
    sendError('Messages array is required');
}

// Validate messages format
foreach ($messages as &$msg) {
    if (!isset($msg['role']) || !isset($msg['content'])) {
        sendError('Each message must have role and content');
    }
    
    if (!in_array($msg['role'], ['system', 'user', 'assistant', 'tool'])) {
        sendError('Invalid role. Must be system, user, assistant, or tool');
    }
}

// Prepend system prompt if provided
if ($systemPrompt && !empty(trim($systemPrompt))) {
    array_unshift($messages, [
        'role' => 'system',
        'content' => $systemPrompt
    ]);
}

// Build the request payload
$payload = [
    'model' => $model,
    'messages' => $messages,
    'stream' => true
];

// Add options if provided
if (!empty($options)) {
    $payload['options'] = [];
    
    if (isset($options['temperature'])) {
        $payload['options']['temperature'] = (float)$options['temperature'];
    }
    if (isset($options['num_predict'])) {
        $payload['options']['num_predict'] = (int)$options['num_predict'];
    }
    if (isset($options['num_ctx'])) {
        $payload['options']['num_ctx'] = (int)$options['num_ctx'];
    }
    if (isset($options['top_p'])) {
        $payload['options']['top_p'] = (float)$options['top_p'];
    }
    if (isset($options['top_k'])) {
        $payload['options']['top_k'] = (int)$options['top_k'];
    }
    if (isset($options['seed'])) {
        $payload['options']['seed'] = (int)$options['seed'];
    }
    if (isset($options['repeat_penalty'])) {
        $payload['options']['repeat_penalty'] = (float)$options['repeat_penalty'];
    }
}

// Add keep_alive if provided
if (isset($options['keep_alive'])) {
    $payload['keep_alive'] = $options['keep_alive'];
}

// Add tools if provided (for function calling)
if (!empty($tools)) {
    $payload['tools'] = $tools;
}

// Add format for structured output
if ($format) {
    $payload['format'] = $format;
}

// Send initial event
sendSSE('start', [
    'model' => $model,
    'timestamp' => date('c')
]);

// Initialize response collection
$assistantContent = '';
$startTime = microtime(true);

// Make streaming request to Ollama
$url = $baseUrl . '/api/chat';
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_CONNECTTIMEOUT => OLLAMA_TIMEOUT_CONNECT,
    CURLOPT_TIMEOUT => OLLAMA_TIMEOUT_EXECUTE,
    CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$assistantContent) {
        // Ollama sends NDJSON (newline-delimited JSON)
        $lines = explode("\n", trim($chunk));
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $data = json_decode($line, true);
            if (!$data) continue;
            
            // Check for tool calls
            if (isset($data['message']['tool_calls'])) {
                sendSSE('tool_call', [
                    'tool_calls' => $data['message']['tool_calls']
                ]);
            }
            
            // Send content token
            if (isset($data['message']['content'])) {
                sendSSE('token', [
                    'content' => $data['message']['content']
                ]);
                $assistantContent .= $data['message']['content'];
            }
            
            // Check if done
            if (isset($data['done']) && $data['done'] === true) {
                sendSSE('complete', [
                    'model' => $data['model'] ?? '',
                    'created_at' => $data['created_at'] ?? null,
                    'total_duration' => $data['total_duration'] ?? null,
                    'load_duration' => $data['load_duration'] ?? null,
                    'prompt_eval_count' => $data['prompt_eval_count'] ?? null,
                    'prompt_eval_duration' => $data['prompt_eval_duration'] ?? null,
                    'eval_count' => $data['eval_count'] ?? null,
                    'eval_duration' => $data['eval_duration'] ?? null
                ]);
            }
        }
        
        return strlen($chunk);
    }
]);

$result = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

if ($errno) {
    sendSSE('error', ['error' => "Connection error: {$error}"]);
}

// Save to history if successful
if (!$errno && !empty($assistantContent)) {
    $duration = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds
    
    // Log API call with response
    $db = new Database();
    $db->addApiLog('/api/chat (stream)', 'POST', [
        'model' => $model,
        'message_count' => count($messages),
        'streaming' => true
    ], [
        'success' => true,
        'message' => ['content' => $assistantContent]
    ], $duration);
    
    // Save to history if enabled
    $settings = $db->getAllSettings();
    if (($settings['maxHistoryItems'] ?? 100) > 0) {
        $allMessages = $messages;
        $allMessages[] = [
            'role' => 'assistant',
            'content' => $assistantContent
        ];
        $db->addChatHistory($model, $allMessages);
    }
} else {
    // Log failed call
    $db = new Database();
    $db->addApiLog('/api/chat (stream)', 'POST', [
        'model' => $model,
        'message_count' => count($messages),
        'streaming' => true
    ], ['error' => $error ?: 'No response'], null);
}

// Final done event
sendSSE('done', ['done' => true]);
