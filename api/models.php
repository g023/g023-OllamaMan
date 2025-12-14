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
                        // Handle both "PARAMETER key value" and "key value" formats
                        if (strpos($line, 'PARAMETER ') === 0) {
                            $line = substr($line, 10); // Remove "PARAMETER " prefix
                        }
                        $parts = preg_split('/\s+/', $line, 2);
                        if (count($parts) == 2) {
                            $key = trim($parts[0]);
                            $value = trim($parts[1]);
                            
                            // Handle array values (like stop sequences)
                            if (strpos($value, '[') === 0 && strrpos($value, ']') === strlen($value) - 1) {
                                // Try to parse as JSON array
                                $decoded = json_decode($value, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $parameters[$key] = $decoded;
                                } else {
                                    // Remove quotes and brackets if not valid JSON
                                    $value = trim($value, '"\'[]');
                                    $parameters[$key] = $value;
                                }
                            } else {
                                // Remove quotes if present
                                $value = trim($value, '"\'');
                                $parameters[$key] = $value;
                            }
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
    
    case 'create_advanced':
        $newModelName = $input['name'] ?? '';
        $fromModel = $input['from'] ?? '';
        $parameters = $input['parameters'] ?? [];
        $system = $input['system'] ?? null;
        $template = $input['template'] ?? null;
        $messages = $input['messages'] ?? null; // Array of message objects
        
        if (empty($newModelName)) {
            errorResponse('New model name is required', 400);
        }
        
        if (empty($fromModel)) {
            errorResponse('Base model (from) is required', 400);
        }
        
        // Validate model name format (alphanumeric, hyphens, underscores, colons for tags)
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_\-.:\/]*$/', $newModelName)) {
            errorResponse('Invalid model name. Use only letters, numbers, hyphens, underscores, and colons for tags.', 400);
        }
        
        // Sanitize and validate parameters
        $sanitizedParams = [];
        $validParams = [
            'num_ctx' => 'int',
            'num_predict' => 'int',
            'repeat_last_n' => 'int',
            'repeat_penalty' => 'float',
            'temperature' => 'float',
            'top_k' => 'int',
            'top_p' => 'float',
            'min_p' => 'float',
            'seed' => 'int',
            'stop' => 'array',
            'num_keep' => 'int',
            'typical_p' => 'float',
            'presence_penalty' => 'float',
            'frequency_penalty' => 'float',
            'penalize_newline' => 'bool',
            'num_batch' => 'int',
            'num_gpu' => 'int',
            'main_gpu' => 'int',
            'num_thread' => 'int'
        ];
        
        foreach ($parameters as $key => $value) {
            if (!isset($validParams[$key])) {
                continue; // Skip unknown parameters
            }
            
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Type conversion and validation
            switch ($validParams[$key]) {
                case 'int':
                    $sanitizedParams[$key] = intval($value);
                    break;
                case 'float':
                    $sanitizedParams[$key] = floatval($value);
                    break;
                case 'bool':
                    $sanitizedParams[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'array':
                    // Handle stop sequences - can be string or array
                    if (is_array($value)) {
                        $sanitizedParams[$key] = array_filter($value, function($v) {
                            return !empty(trim($v));
                        });
                    } elseif (!empty(trim($value))) {
                        $sanitizedParams[$key] = [$value];
                    }
                    break;
                default:
                    $sanitizedParams[$key] = $value;
            }
        }
        
        // Validate messages if provided
        $sanitizedMessages = null;
        if (!empty($messages) && is_array($messages)) {
            $sanitizedMessages = [];
            $validRoles = ['user', 'assistant', 'system'];
            
            foreach ($messages as $msg) {
                if (is_array($msg) && isset($msg['role']) && isset($msg['content'])) {
                    $role = strtolower(trim($msg['role']));
                    $content = trim($msg['content']);
                    
                    if (in_array($role, $validRoles) && !empty($content)) {
                        $sanitizedMessages[] = [
                            'role' => $role,
                            'content' => $content
                        ];
                    }
                }
            }
            
            // Only include if we have valid messages
            if (empty($sanitizedMessages)) {
                $sanitizedMessages = null;
            }
        }
        
        $result = $ollama->createModelAdvanced($newModelName, $fromModel, $sanitizedParams, $system, $template, $sanitizedMessages);
        
        if ($result['success']) {
            successResponse([
                'name' => $newModelName,
                'from' => $fromModel,
                'parameters' => $sanitizedParams,
                'messages' => $sanitizedMessages,
                'status' => 'created',
                'message' => "Model '$newModelName' created successfully from '$fromModel'",
                'duration' => $result['duration']
            ]);
        } else {
            errorResponse($result['error']);
        }
        break;
        
    default:
        errorResponse('Invalid action', 400);
}
