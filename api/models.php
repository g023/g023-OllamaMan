<?php
/**
 * Ollama Manager - Models API Endpoint
 * 
 * Handles model listing, info, pull, delete, and copy operations.
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

// Get action from query parameter or POST body
$action = $_GET['action'] ?? 'list';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'list':
        $result = $ollama->listModels();
        if ($result['success']) {
            successResponse([
                'models' => $result['data']['models'] ?? [],
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'running':
        $result = $ollama->listRunningModels();
        if ($result['success']) {
            successResponse([
                'models' => $result['data']['models'] ?? [],
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'show':
        $modelName = $input['model'] ?? $_GET['model'] ?? '';
        if (empty($modelName)) {
            errorResponse('Model name is required', 400);
        }
        
        $result = $ollama->showModel($modelName);
        if ($result['success']) {
            // Parse and structure the model info
            $data = $result['data'];
            
            // Extract parameters from modelfile if present
            $parameters = [];
            if (isset($data['parameters'])) {
                $lines = explode("\n", $data['parameters']);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $parts = preg_split('/\s+/', $line, 2);
                        if (count($parts) == 2) {
                            $parameters[$parts[0]] = $parts[1];
                        }
                    }
                }
            }
            
            successResponse([
                'model' => $modelName,
                'license' => $data['license'] ?? null,
                'modelfile' => $data['modelfile'] ?? null,
                'parameters' => $parameters,
                'template' => $data['template'] ?? null,
                'details' => $data['details'] ?? [],
                'model_info' => $data['model_info'] ?? [],
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'pull':
        $modelName = $input['model'] ?? '';
        if (empty($modelName)) {
            errorResponse('Model name is required', 400);
        }
        
        // Note: This is a blocking call and may take a long time
        $result = $ollama->pullModel($modelName);
        if ($result['success']) {
            successResponse([
                'model' => $modelName,
                'status' => 'success',
                'message' => "Model '$modelName' pulled successfully",
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'delete':
        $modelName = $input['model'] ?? '';
        if (empty($modelName)) {
            errorResponse('Model name is required', 400);
        }
        
        $result = $ollama->deleteModel($modelName);
        if ($result['success']) {
            successResponse([
                'model' => $modelName,
                'status' => 'deleted',
                'message' => "Model '$modelName' deleted successfully",
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    case 'copy':
        $source = $input['source'] ?? '';
        $destination = $input['destination'] ?? '';
        
        if (empty($source) || empty($destination)) {
            errorResponse('Source and destination model names are required', 400);
        }
        
        $result = $ollama->copyModel($source, $destination);
        if ($result['success']) {
            successResponse([
                'source' => $source,
                'destination' => $destination,
                'status' => 'copied',
                'message' => "Model copied from '$source' to '$destination'",
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    default:
        errorResponse('Invalid action', 400);
}
