<?php
/**
 * Ollama Manager - History API Endpoint
 *
 * Manages chat history using SQLite database.
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
        $history = $db->getChatHistory(50);

        successResponse([
            'history' => $history,
            'count' => count($history)
        ]);
        break;

    case 'get':
        $id = $_GET['id'] ?? '';

        if (empty($id)) {
            errorResponse('History ID is required', 400);
        }

        $chat = $db->getChatById($id);

        if ($chat) {
            successResponse($chat);
        } else {
            errorResponse('History item not found', 404);
        }
        break;

    case 'delete':
        $id = $_GET['id'] ?? '';

        if (empty($id)) {
            errorResponse('History ID is required', 400);
        }

        if ($db->deleteChat($id)) {
            successResponse(['message' => 'History item deleted']);
        } else {
            errorResponse('Failed to delete history item');
        }
        break;

    case 'clear':
        if ($db->clearChatHistory()) {
            successResponse(['message' => 'History cleared']);
        } else {
            errorResponse('Failed to clear history');
        }
        break;

    case 'export':
        $history = $db->getChatHistory(1000); // Export more items

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ollama_chat_history_' . date('Y-m-d') . '.json"');
        echo json_encode($history, JSON_PRETTY_PRINT);
        exit;
        break;

    default:
        errorResponse('Invalid action', 400);
}
