<?php
/**
 * Database Initialization and Migration Script
 *
 * This script initializes the SQLite database and migrates data from JSON files.
 * Run this once when first upgrading from JSON to SQLite storage.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Ensure data directory exists
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Initialize database
$db = new Database();

// Migration flags
$migrated = false;
$errors = [];

// Migrate settings
$settingsFile = $dataDir . '/settings.json';
if (file_exists($settingsFile)) {
    try {
        if ($db->migrateSettingsFromJson($settingsFile)) {
            echo "✓ Migrated settings from JSON\n";
            // Backup original file
            rename($settingsFile, $settingsFile . '.backup');
            $migrated = true;
        } else {
            $errors[] = "Failed to migrate settings";
        }
    } catch (Exception $e) {
        $errors[] = "Settings migration error: " . $e->getMessage();
    }
}

// Migrate chat history
$chatFile = $dataDir . '/chat_history.json';
if (file_exists($chatFile)) {
    try {
        if ($db->migrateChatHistoryFromJson($chatFile)) {
            echo "✓ Migrated chat history from JSON\n";
            // Backup original file
            rename($chatFile, $chatFile . '.backup');
            $migrated = true;
        } else {
            $errors[] = "Failed to migrate chat history";
        }
    } catch (Exception $e) {
        $errors[] = "Chat history migration error: " . $e->getMessage();
    }
}

// Migrate API logs
$logsFile = $dataDir . '/api_logs.json';
if (file_exists($logsFile)) {
    try {
        if ($db->migrateApiLogsFromJson($logsFile)) {
            echo "✓ Migrated API logs from JSON\n";
            // Backup original file
            rename($logsFile, $logsFile . '.backup');
            $migrated = true;
        } else {
            $errors[] = "Failed to migrate API logs";
        }
    } catch (Exception $e) {
        $errors[] = "API logs migration error: " . $e->getMessage();
    }
}

// Show results
if ($migrated) {
    echo "\n🎉 Migration completed successfully!\n";
    echo "Original JSON files have been backed up with .backup extension.\n";
} else {
    echo "\nℹ️  No JSON files found to migrate, or fresh database created.\n";
}

if (!empty($errors)) {
    echo "\n❌ Migration errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Show database stats
echo "\n📊 Database Statistics:\n";
$stats = $db->getStats();
echo "  Settings: {$stats['settings']} entries\n";
echo "  Chat History: {$stats['chat_history']} conversations\n";
echo "  API Logs: {$stats['api_logs']} requests\n";
echo "  Database Size: " . round($stats['db_size'] / 1024, 2) . " KB\n";

echo "\n✅ Database initialization complete!\n";
?>