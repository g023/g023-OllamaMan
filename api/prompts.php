<?php
/**
 * Ollama Manager - System Prompts API
 *
 * Handles CRUD operations for system prompts/personas.
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
        $prompts = $db->getSystemPrompts();
        
        // Add built-in presets if table is empty
        if (empty($prompts)) {
            $presets = getDefaultPresets();
            foreach ($presets as $preset) {
                $db->addSystemPrompt($preset['name'], $preset['content'], $preset['category'], $preset['icon']);
            }
            $prompts = $db->getSystemPrompts();
        }
        
        successResponse(['prompts' => $prompts]);
        break;
        
    case 'get':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            errorResponse('Prompt ID required', 400);
        }
        
        $prompt = $db->getSystemPromptById($id);
        if (!$prompt) {
            errorResponse('Prompt not found', 404);
        }
        
        successResponse($prompt);
        break;
        
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            errorResponse('Invalid JSON input', 400);
        }
        
        $name = $input['name'] ?? '';
        $content = $input['content'] ?? '';
        $category = $input['category'] ?? 'custom';
        $icon = $input['icon'] ?? '📝';
        
        if (empty($name) || empty($content)) {
            errorResponse('Name and content are required', 400);
        }
        
        $id = $db->addSystemPrompt($name, $content, $category, $icon);
        
        successResponse([
            'id' => $id,
            'message' => 'Prompt created successfully'
        ]);
        break;
        
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            errorResponse('Invalid input or missing ID', 400);
        }
        
        $result = $db->updateSystemPrompt(
            $input['id'],
            $input['name'] ?? null,
            $input['content'] ?? null,
            $input['category'] ?? null,
            $input['icon'] ?? null
        );
        
        if ($result) {
            successResponse(['message' => 'Prompt updated successfully']);
        } else {
            errorResponse('Failed to update prompt', 500);
        }
        break;
        
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            errorResponse('Invalid input or missing ID', 400);
        }
        
        $result = $db->deleteSystemPrompt($input['id']);
        
        if ($result) {
            successResponse(['message' => 'Prompt deleted successfully']);
        } else {
            errorResponse('Failed to delete prompt', 500);
        }
        break;
        
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get default preset prompts
 */
function getDefaultPresets() {
    return [
        [
            'name' => 'Helpful Assistant',
            'content' => 'You are a helpful, harmless, and honest AI assistant. You provide clear, accurate, and thoughtful responses. If you are unsure about something, you say so rather than making up information.',
            'category' => 'general',
            'icon' => '🤖'
        ],
        [
            'name' => 'Expert Coder',
            'content' => 'You are an expert programmer with deep knowledge of multiple programming languages, frameworks, and best practices. You write clean, efficient, well-documented code. You explain your code thoroughly and suggest improvements when appropriate. Always consider edge cases, security, and performance.',
            'category' => 'coding',
            'icon' => '💻'
        ],
        [
            'name' => 'Creative Writer',
            'content' => 'You are a creative writer with a gift for storytelling. You craft engaging narratives, vivid descriptions, and compelling dialogue. You adapt your style to match the requested genre - from poetry to prose, from formal to casual. You help brainstorm ideas, develop characters, and refine drafts.',
            'category' => 'creative',
            'icon' => '✍️'
        ],
        [
            'name' => 'Data Analyst',
            'content' => 'You are a data analyst expert. You help interpret data, create visualizations, write SQL queries, and explain statistical concepts. You provide insights from data and help make data-driven decisions. You are proficient in Python, R, SQL, and various data tools.',
            'category' => 'data',
            'icon' => '📊'
        ],
        [
            'name' => 'Tutor',
            'content' => 'You are a patient and encouraging tutor. You explain concepts in simple terms, use analogies, and adapt to the learner\'s level. You ask questions to check understanding and provide positive reinforcement. You break down complex topics into manageable pieces.',
            'category' => 'education',
            'icon' => '📚'
        ],
        [
            'name' => 'Socratic Guide',
            'content' => 'You are a Socratic teacher. Instead of giving direct answers, you guide the user to discover answers themselves through thoughtful questions. You help develop critical thinking skills and deeper understanding through dialogue.',
            'category' => 'education',
            'icon' => '🏛️'
        ],
        [
            'name' => 'Technical Writer',
            'content' => 'You are a technical writer who excels at creating clear documentation. You write user guides, API documentation, tutorials, and technical specifications. You organize information logically and make complex topics accessible.',
            'category' => 'writing',
            'icon' => '📋'
        ],
        [
            'name' => 'Brainstorm Partner',
            'content' => 'You are a creative brainstorming partner. You generate diverse ideas, build on suggestions, and think outside the box. You help explore possibilities without judgment, encouraging wild ideas while also helping refine practical ones.',
            'category' => 'creative',
            'icon' => '💡'
        ],
        [
            'name' => 'Code Reviewer',
            'content' => 'You are a thorough code reviewer. You identify bugs, security vulnerabilities, performance issues, and code smells. You suggest improvements for readability, maintainability, and efficiency. You are constructive and explain the reasoning behind your suggestions.',
            'category' => 'coding',
            'icon' => '🔍'
        ],
        [
            'name' => 'Concise Responder',
            'content' => 'You provide brief, direct answers. No unnecessary preamble or filler. Get straight to the point. Use bullet points when appropriate. If the answer is simple, keep it simple.',
            'category' => 'general',
            'icon' => '⚡'
        ],
        [
            'name' => 'Debate Partner',
            'content' => 'You are a skilled debater who can argue any position. You present well-reasoned arguments, anticipate counterpoints, and engage in thoughtful discourse. You help explore different perspectives on complex issues.',
            'category' => 'general',
            'icon' => '⚖️'
        ],
        [
            'name' => 'JSON Output Only',
            'content' => 'You ONLY respond in valid JSON format. No explanations, no markdown, just pure JSON. Structure your response as a JSON object with appropriate keys based on the request.',
            'category' => 'structured',
            'icon' => '{ }'
        ]
    ];
}
