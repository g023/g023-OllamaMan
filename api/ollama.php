<?php
/**
 * Ollama Manager - Ollama API Wrapper Class
 *
 * A comprehensive PHP class to interact with the Ollama API.
 * Handles all communication, error handling, and response parsing.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class OllamaAPI {
    private $baseUrl;
    private $timeout;
    private $connectTimeout;
    private $db;

    public function __construct($host = null, $port = null) {
        $host = $host ?: OLLAMA_HOST;
        $port = $port ?: OLLAMA_PORT;
        $this->baseUrl = 'http://' . $host . ':' . $port;
        $this->timeout = OLLAMA_TIMEOUT_EXECUTE;
        $this->connectTimeout = OLLAMA_TIMEOUT_CONNECT;
        $this->db = new Database();
    }
    
    /**
     * Make a cURL request to the Ollama API
     */
    private function request($endpoint, $method = 'GET', $data = null, $timeout = null) {
        $url = $this->baseUrl . $endpoint;
        $startTime = microtime(true);
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $timeout ?: $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);
        
        $duration = round((microtime(true) - $startTime) * 1000);
        
        // Log the API call to database
        $result = $response ? json_decode($response, true) : null;
        $this->db->addApiLog($endpoint, $method, $data, $result, $duration);
        
        if ($errno) {
            return [
                'success' => false,
                'error' => $this->getCurlErrorMessage($errno, $error),
                'errno' => $errno,
                'duration' => $duration
            ];
        }
        
        if ($httpCode >= 400) {
            return [
                'success' => false,
                'error' => $result['error'] ?? "HTTP Error: $httpCode",
                'httpCode' => $httpCode,
                'duration' => $duration
            ];
        }
        
        return [
            'success' => true,
            'data' => $result,
            'httpCode' => $httpCode,
            'duration' => $duration
        ];
    }
    
    /**
     * Get human-readable cURL error message
     */
    private function getCurlErrorMessage($errno, $error) {
        $messages = [
            CURLE_COULDNT_CONNECT => 'Cannot connect to Ollama server. Please check if the server is running and accessible.',
            CURLE_OPERATION_TIMEDOUT => 'Connection timed out. The server might be busy or the operation is taking too long.',
            CURLE_COULDNT_RESOLVE_HOST => 'Cannot resolve hostname. Please check the server address.',
            CURLE_SSL_CONNECT_ERROR => 'SSL connection error. Check your SSL configuration.',
        ];
        
        return $messages[$errno] ?? "Connection error: $error";
    }
    
    /**
     * Check server connectivity
     */
    public function checkConnection() {
        $result = $this->request('/api/version', 'GET', null, 5);
        
        if ($result['success']) {
            return [
                'connected' => true,
                'version' => $result['data']['version'] ?? 'Unknown',
                'duration' => $result['duration']
            ];
        }
        
        return [
            'connected' => false,
            'error' => $result['error'],
            'duration' => $result['duration']
        ];
    }
    
    /**
     * Get Ollama version
     */
    public function getVersion() {
        return $this->request('/api/version');
    }
    
    /**
     * List available models
     */
    public function listModels() {
        $result = $this->request('/api/tags');
        
        if ($result['success'] && isset($result['data']['models'])) {
            // Sort models by name
            usort($result['data']['models'], function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            
            // Add formatted size
            foreach ($result['data']['models'] as &$model) {
                $model['size_formatted'] = $this->formatBytes($model['size'] ?? 0);
                $model['modified_formatted'] = $this->formatDate($model['modified_at'] ?? '');
            }
        }
        
        return $result;
    }
    
    /**
     * List running models
     */
    public function listRunningModels() {
        $result = $this->request('/api/ps');
        
        if ($result['success'] && isset($result['data']['models'])) {
            foreach ($result['data']['models'] as &$model) {
                $model['size_vram_formatted'] = $this->formatBytes($model['size_vram'] ?? 0);
                $model['size_formatted'] = $this->formatBytes($model['size'] ?? 0);
            }
        }
        
        return $result;
    }
    
    /**
     * Get model information
     */
    public function showModel($modelName) {
        return $this->request('/api/show', 'POST', ['model' => $modelName]);
    }
    
    /**
     * Pull a model
     */
    public function pullModel($modelName) {
        return $this->request('/api/pull', 'POST', ['model' => $modelName, 'stream' => false], OLLAMA_TIMEOUT_PULL);
    }
    
    /**
     * Delete a model
     */
    public function deleteModel($modelName) {
        return $this->request('/api/delete', 'DELETE', ['model' => $modelName]);
    }
    
    /**
     * Copy a model
     */
    public function copyModel($source, $destination) {
        return $this->request('/api/copy', 'POST', [
            'source' => $source,
            'destination' => $destination
        ]);
    }
    
    /**
     * Generate completion
     */
    public function generate($model, $prompt, $options = []) {
        $data = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false
        ];
        
        // Add optional parameters
        if (isset($options['system'])) $data['system'] = $options['system'];
        if (isset($options['temperature'])) $data['options']['temperature'] = $options['temperature'];
        if (isset($options['num_predict'])) $data['options']['num_predict'] = $options['num_predict'];
        if (isset($options['top_p'])) $data['options']['top_p'] = $options['top_p'];
        if (isset($options['top_k'])) $data['options']['top_k'] = $options['top_k'];
        if (isset($options['context'])) $data['context'] = $options['context'];
        
        return $this->request('/api/generate', 'POST', $data);
    }
    
    /**
     * Chat completion
     */
    public function chat($model, $messages, $options = []) {
        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false
        ];
        
        // Add optional parameters
        if (isset($options['temperature'])) $data['options']['temperature'] = $options['temperature'];
        if (isset($options['num_predict'])) $data['options']['num_predict'] = $options['num_predict'];
        if (isset($options['top_p'])) $data['options']['top_p'] = $options['top_p'];
        if (isset($options['top_k'])) $data['options']['top_k'] = $options['top_k'];
        if (isset($options['system'])) {
            // Prepend system message
            array_unshift($data['messages'], [
                'role' => 'system',
                'content' => $options['system']
            ]);
        }
        
        return $this->request('/api/chat', 'POST', $data);
    }
    
    /**
     * Generate embeddings
     */
    public function embed($model, $input) {
        return $this->request('/api/embed', 'POST', [
            'model' => $model,
            'input' => $input
        ]);
    }
    
    /**
     * Create a model from Modelfile
     */
    public function createModel($name, $modelfile) {
        return $this->request('/api/create', 'POST', [
            'name' => $name,
            'modelfile' => $modelfile,
            'stream' => false
        ], OLLAMA_TIMEOUT_PULL);
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
    
    /**
     * Format date to human readable
     */
    private function formatDate($dateString) {
        if (empty($dateString)) return 'Unknown';
        
        try {
            $date = new DateTime($dateString);
            return $date->format('M j, Y g:i A');
        } catch (Exception $e) {
            return $dateString;
        }
    }
}
