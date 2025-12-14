<?php
/**
 * Ollama Manager - Status API Endpoint
 * 
 * Returns server status, connection info, and running models.
 */

require_once __DIR__ . '/ollama.php';
require_once __DIR__ . '/database.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get server settings
$db = new Database();
$settings = $db->getAllSettings();
$host = $settings['ollamaHost'] ?? OLLAMA_HOST;
$port = $settings['ollamaPort'] ?? OLLAMA_PORT;

$ollama = new OllamaAPI($host, $port);

// Get connection status
$connection = $ollama->checkConnection();

// Get running models if connected
$runningModels = [];
$availableModels = [];
$totalModels = 0;
$totalSize = 0;

if ($connection['connected']) {
    $running = $ollama->listRunningModels();
    if ($running['success']) {
        $runningModels = $running['data']['models'] ?? [];
    }
    
    $models = $ollama->listModels();
    if ($models['success']) {
        $availableModels = $models['data']['models'] ?? [];
        $totalModels = count($availableModels);
        
        foreach ($availableModels as $model) {
            $totalSize += $model['size'] ?? 0;
        }
    }
}

// Format total size
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

$response = [
    'success' => true,
    'data' => [
        'server' => [
            'host' => OLLAMA_HOST,
            'port' => OLLAMA_PORT,
            'url' => OLLAMA_BASE_URL,
            'connected' => $connection['connected'],
            'version' => $connection['version'] ?? null,
            'latency' => $connection['duration'] ?? null,
            'error' => $connection['error'] ?? null
        ],
        'models' => [
            'total' => $totalModels,
            'running' => count($runningModels),
            'runningList' => $runningModels,
            'totalSize' => formatBytes($totalSize),
            'totalSizeBytes' => $totalSize
        ],
        'app' => [
            'name' => APP_NAME,
            'version' => APP_VERSION
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'uptime' => time()
    ]
];

echo json_encode($response);
