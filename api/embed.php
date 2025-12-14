<?php
/**
 * Ollama Manager - Embeddings API Endpoint
 * 
 * Generates vector embeddings from text.
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
$text = $input['input'] ?? $input['text'] ?? '';

if (empty($model)) {
    errorResponse('Model is required', 400);
}

if (empty($text)) {
    errorResponse('Input text is required', 400);
}

// Get server settings
$db = new Database();
$settings = $db->getAllSettings();
$host = $settings['ollamaHost'] ?? OLLAMA_HOST;
$port = $settings['ollamaPort'] ?? OLLAMA_PORT;

$ollama = new OllamaAPI($host, $port);

$result = $ollama->embed($model, $text);

if ($result['success']) {
    $embeddings = $result['data']['embeddings'] ?? [];
    $dimensions = !empty($embeddings) ? count($embeddings[0]) : 0;
    
    // Calculate some basic stats for visualization
    $stats = [];
    if ($dimensions > 0) {
        $embedding = $embeddings[0];
        $stats = [
            'min' => min($embedding),
            'max' => max($embedding),
            'mean' => array_sum($embedding) / $dimensions,
            'dimensions' => $dimensions
        ];
        
        // Calculate standard deviation
        $variance = 0;
        foreach ($embedding as $val) {
            $variance += pow($val - $stats['mean'], 2);
        }
        $stats['std_dev'] = sqrt($variance / $dimensions);
        
        // Get a sample of values for visualization (first 50)
        $stats['sample'] = array_slice($embedding, 0, 50);
    }
    
    successResponse([
        'embeddings' => $embeddings,
        'model' => $result['data']['model'] ?? $model,
        'dimensions' => $dimensions,
        'stats' => $stats,
        'duration' => $result['duration']
    ]);
} else {
    errorResponse($result['error']);
}
