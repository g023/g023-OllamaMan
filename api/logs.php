<?php
/**
 * Ollama Manager - Logs API Endpoint
 *
 * Retrieves API logs for debugging and monitoring using SQLite database.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = new Database();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $logs = $db->getApiLogs(100); // Get last 100 logs

        successResponse([
            'logs' => $logs,
            'total' => count($logs),
            'limit' => 100
        ]);
        break;

    case 'clear':
        if ($db->clearApiLogs()) {
            successResponse(['message' => 'Logs cleared']);
        } else {
            errorResponse('Failed to clear logs');
        }
        break;

    case 'stats':
        $logs = $db->getApiLogs(1000); // Get more logs for stats

        // Calculate statistics
        $stats = [
            'total_requests' => count($logs),
            'endpoints' => [],
            'avg_duration' => 0,
            'max_duration' => 0,
            'min_duration' => PHP_INT_MAX
        ];

        $totalDuration = 0;

        foreach ($logs as $log) {
            $endpoint = $log['endpoint'] ?? 'unknown';
            $duration = $log['duration_ms'] ?? 0;

            if (!isset($stats['endpoints'][$endpoint])) {
                $stats['endpoints'][$endpoint] = 0;
            }
            $stats['endpoints'][$endpoint]++;

            $totalDuration += $duration;
            $stats['max_duration'] = max($stats['max_duration'], $duration);
            $stats['min_duration'] = min($stats['min_duration'], $duration);
        }

        if (count($logs) > 0) {
            $stats['avg_duration'] = round($totalDuration / count($logs), 2);
        }

        if ($stats['min_duration'] === PHP_INT_MAX) {
            $stats['min_duration'] = 0;
        }

        successResponse($stats);
        break;

    default:
        errorResponse('Invalid action', 400);
}
