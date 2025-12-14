<?php
/**
 * Database Class - SQLite Wrapper for Ollama Manager
 *
 * Handles all database operations for settings, chat history, and API logs.
 */

class Database {
    private $pdo;
    private $dbPath;

    public function __construct($dbPath = null) {
        $this->dbPath = $dbPath ?: __DIR__ . '/../data/ollama_manager.db';
        $this->connect();
        $this->initTables();
    }

    /**
     * Connect to SQLite database
     */
    private function connect() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Initialize database tables
     */
    private function initTables() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS chat_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                model TEXT NOT NULL,
                messages TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                title TEXT,
                system_prompt TEXT,
                starred INTEGER DEFAULT 0,
                tags TEXT,
                token_count INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS api_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                endpoint TEXT NOT NULL,
                method TEXT NOT NULL,
                request_data TEXT,
                response_data TEXT,
                response_preview TEXT,
                duration_ms INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS system_prompts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                content TEXT NOT NULL,
                category TEXT DEFAULT 'custom',
                icon TEXT DEFAULT '📝',
                is_builtin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS chat_tools (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                description TEXT,
                parameters TEXT,
                handler TEXT,
                is_enabled INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS message_ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER,
                message_index INTEGER,
                rating INTEGER,
                feedback TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (chat_id) REFERENCES chat_history(id)
            );

            -- Create indexes for better performance
            CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key);
            CREATE INDEX IF NOT EXISTS idx_chat_history_timestamp ON chat_history(timestamp);
            CREATE INDEX IF NOT EXISTS idx_chat_history_starred ON chat_history(starred);
            CREATE INDEX IF NOT EXISTS idx_api_logs_timestamp ON api_logs(timestamp);
            CREATE INDEX IF NOT EXISTS idx_api_logs_endpoint ON api_logs(endpoint);
            CREATE INDEX IF NOT EXISTS idx_system_prompts_category ON system_prompts(category);
        ");
    }

    // ============================================
    // SETTINGS METHODS
    // ============================================

    /**
     * Get all settings as associative array
     */
    public function getAllSettings() {
        $stmt = $this->pdo->query("SELECT key, value FROM settings");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['key']] = json_decode($row['value'], true) ?? $row['value'];
        }
        return $settings;
    }

    /**
     * Get a specific setting
     */
    public function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        if ($result) {
            return json_decode($result['value'], true) ?? $result['value'];
        }

        return $default;
    }

    /**
     * Set a setting
     */
    public function setSetting($key, $value) {
        $jsonValue = is_array($value) || is_object($value) ? json_encode($value) : $value;

        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        return $stmt->execute([$key, $jsonValue]);
    }

    /**
     * Set multiple settings
     */
    public function setSettings($settings) {
        $this->pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $this->setSetting($key, $value);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // ============================================
    // CHAT HISTORY METHODS
    // ============================================

    /**
     * Get all chat history
     */
    public function getChatHistory($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT id, model, messages, timestamp, title
            FROM chat_history
            ORDER BY timestamp DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Add a chat conversation
     */
    public function addChatHistory($model, $messages, $title = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO chat_history (model, messages, title)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$model, json_encode($messages), $title]);
    }

    /**
     * Get a specific chat by ID
     */
    public function getChatById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM chat_history WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        if ($result) {
            $result['messages'] = json_decode($result['messages'], true);
        }

        return $result;
    }

    /**
     * Delete a chat by ID
     */
    public function deleteChat($id) {
        $stmt = $this->pdo->prepare("DELETE FROM chat_history WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Clear all chat history
     */
    public function clearChatHistory() {
        return $this->pdo->exec("DELETE FROM chat_history") !== false;
    }

    /**
     * Star/unstar a chat
     */
    public function toggleChatStar($id) {
        $stmt = $this->pdo->prepare("UPDATE chat_history SET starred = NOT starred WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Update chat title
     */
    public function updateChatTitle($id, $title) {
        $stmt = $this->pdo->prepare("UPDATE chat_history SET title = ? WHERE id = ?");
        return $stmt->execute([$title, $id]);
    }

    /**
     * Search chat history
     */
    public function searchChatHistory($query, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT id, model, messages, timestamp, title, starred
            FROM chat_history
            WHERE messages LIKE ? OR title LIKE ?
            ORDER BY timestamp DESC
            LIMIT ?
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    // ============================================
    // SYSTEM PROMPTS METHODS
    // ============================================

    /**
     * Get all system prompts
     */
    public function getSystemPrompts() {
        $stmt = $this->pdo->query("SELECT * FROM system_prompts ORDER BY category, name");
        return $stmt->fetchAll();
    }

    /**
     * Get system prompt by ID
     */
    public function getSystemPromptById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM system_prompts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Add a system prompt
     */
    public function addSystemPrompt($name, $content, $category = 'custom', $icon = '📝') {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_prompts (name, content, category, icon)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $content, $category, $icon]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Update a system prompt
     */
    public function updateSystemPrompt($id, $name = null, $content = null, $category = null, $icon = null) {
        $updates = [];
        $params = [];

        if ($name !== null) {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        if ($content !== null) {
            $updates[] = "content = ?";
            $params[] = $content;
        }
        if ($category !== null) {
            $updates[] = "category = ?";
            $params[] = $category;
        }
        if ($icon !== null) {
            $updates[] = "icon = ?";
            $params[] = $icon;
        }

        if (empty($updates)) return false;

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE system_prompts SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a system prompt
     */
    public function deleteSystemPrompt($id) {
        $stmt = $this->pdo->prepare("DELETE FROM system_prompts WHERE id = ? AND is_builtin = 0");
        return $stmt->execute([$id]);
    }

    // ============================================
    // CHAT TOOLS METHODS
    // ============================================

    /**
     * Get all chat tools
     */
    public function getChatTools($enabledOnly = false) {
        $sql = "SELECT * FROM chat_tools";
        if ($enabledOnly) {
            $sql .= " WHERE is_enabled = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $this->pdo->query($sql);
        $tools = $stmt->fetchAll();
        
        // Parse JSON parameters
        foreach ($tools as &$tool) {
            $tool['parameters'] = json_decode($tool['parameters'], true);
        }
        
        return $tools;
    }

    /**
     * Add a chat tool
     */
    public function addChatTool($name, $description, $parameters, $handler = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO chat_tools (name, description, parameters, handler)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $name,
            $description,
            json_encode($parameters),
            $handler
        ]);
    }

    /**
     * Toggle tool enabled state
     */
    public function toggleChatTool($id) {
        $stmt = $this->pdo->prepare("UPDATE chat_tools SET is_enabled = NOT is_enabled WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Delete a chat tool
     */
    public function deleteChatTool($id) {
        $stmt = $this->pdo->prepare("DELETE FROM chat_tools WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ============================================
    // MESSAGE RATINGS METHODS
    // ============================================

    /**
     * Rate a message
     */
    public function rateMessage($chatId, $messageIndex, $rating, $feedback = null) {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO message_ratings (chat_id, message_index, rating, feedback)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$chatId, $messageIndex, $rating, $feedback]);
    }

    /**
     * Get ratings for a chat
     */
    public function getChatRatings($chatId) {
        $stmt = $this->pdo->prepare("SELECT * FROM message_ratings WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        return $stmt->fetchAll();
    }

    // ============================================
    // API LOGS METHODS
    // ============================================

    /**
     * Get API logs
     */
    public function getApiLogs($limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT id, endpoint, method, request_data, response_data,
                   response_preview, duration_ms, timestamp
            FROM api_logs
            ORDER BY timestamp DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Add an API log entry
     */
    public function addApiLog($endpoint, $method, $requestData = null, $responseData = null, $durationMs = null) {
        $responsePreview = null;
        if ($responseData) {
            if (is_array($responseData) && isset($responseData['response'])) {
                $responsePreview = substr($responseData['response'], 0, 100);
            } elseif (is_string($responseData)) {
                $responsePreview = substr($responseData, 0, 100);
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO api_logs (endpoint, method, request_data, response_data, response_preview, duration_ms)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $endpoint,
            $method,
            $requestData ? json_encode($requestData) : null,
            $responseData ? json_encode($responseData) : null,
            $responsePreview,
            $durationMs
        ]);
    }

    /**
     * Clear all API logs
     */
    public function clearApiLogs() {
        return $this->pdo->exec("DELETE FROM api_logs") !== false;
    }

    // ============================================
    // MIGRATION METHODS
    // ============================================

    /**
     * Migrate settings from JSON file
     */
    public function migrateSettingsFromJson($jsonFile) {
        if (!file_exists($jsonFile)) return false;

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data) return false;

        return $this->setSettings($data);
    }

    /**
     * Migrate chat history from JSON file
     */
    public function migrateChatHistoryFromJson($jsonFile) {
        if (!file_exists($jsonFile)) return false;

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data || !is_array($data)) return false;

        $this->pdo->beginTransaction();
        try {
            foreach ($data as $chat) {
                $this->addChatHistory(
                    $chat['model'] ?? 'unknown',
                    $chat['messages'] ?? [],
                    $chat['title'] ?? null
                );
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Migrate API logs from JSON file
     */
    public function migrateApiLogsFromJson($jsonFile) {
        if (!file_exists($jsonFile)) return false;

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!$data || !is_array($data)) return false;

        $this->pdo->beginTransaction();
        try {
            foreach ($data as $log) {
                $this->addApiLog(
                    $log['endpoint'] ?? '',
                    $log['method'] ?? 'GET',
                    $log['request_data'] ?? null,
                    $log['response_data'] ?? null,
                    $log['duration_ms'] ?? null
                );
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Get database statistics
     */
    public function getStats() {
        $stats = [];

        // Settings count
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM settings");
        $stats['settings'] = $stmt->fetch()['count'];

        // Chat history count
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM chat_history");
        $stats['chat_history'] = $stmt->fetch()['count'];

        // API logs count
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM api_logs");
        $stats['api_logs'] = $stmt->fetch()['count'];

        // Database file size
        $stats['db_size'] = filesize($this->dbPath);

        return $stats;
    }
}
?>