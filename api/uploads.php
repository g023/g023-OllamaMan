<?php
/**
 * Ollama Manager - File Upload Handler
 *
 * Handles image uploads for multimodal/vision chat.
 * Supports drag-and-drop, clipboard paste, and file input.
 */

require_once __DIR__ . '/config.php';

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$uploadDir = __DIR__ . '/../data/uploads/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$action = $_GET['action'] ?? 'upload';

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    case 'base64':
        handleBase64();
        break;
    case 'delete':
        handleDelete();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Handle file upload
 */
function handleUpload() {
    global $maxFileSize, $allowedTypes, $uploadDir;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['image']['error'] ?? 'Unknown';
        errorResponse("Upload failed with error code: {$errorCode}", 400);
    }
    
    $file = $_FILES['image'];
    
    // Check file size
    if ($file['size'] > $maxFileSize) {
        errorResponse('File too large. Maximum size is 10MB.', 400);
    }
    
    // Check file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        errorResponse('Invalid file type. Allowed: JPEG, PNG, GIF, WebP', 400);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        errorResponse('Failed to save uploaded file', 500);
    }
    
    // Generate Base64 for immediate use
    $base64 = base64_encode(file_get_contents($filepath));
    
    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = $imageInfo[0] ?? 0;
    $height = $imageInfo[1] ?? 0;
    
    successResponse([
        'filename' => $filename,
        'base64' => $base64,
        'mimeType' => $mimeType,
        'size' => $file['size'],
        'width' => $width,
        'height' => $height,
        'url' => 'data/' . $filename
    ]);
}

/**
 * Handle Base64 conversion (for pasted images)
 */
function handleBase64() {
    global $maxFileSize, $allowedTypes, $uploadDir;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data'])) {
        errorResponse('No image data provided', 400);
    }
    
    $dataUrl = $input['data'];
    
    // Parse data URL
    if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $dataUrl, $matches)) {
        $mimeType = $matches[1];
        $base64 = $matches[2];
    } else {
        // Assume it's already just base64
        $base64 = $dataUrl;
        $mimeType = 'image/png';
    }
    
    // Validate mime type
    if (!in_array($mimeType, $allowedTypes)) {
        errorResponse('Invalid image type', 400);
    }
    
    // Decode base64
    $imageData = base64_decode($base64);
    
    if ($imageData === false) {
        errorResponse('Invalid base64 data', 400);
    }
    
    // Check size
    if (strlen($imageData) > $maxFileSize) {
        errorResponse('Image too large. Maximum size is 10MB.', 400);
    }
    
    // Determine extension
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $extensions[$mimeType] ?? 'png';
    
    // Save file
    $filename = uniqid('img_') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (file_put_contents($filepath, $imageData) === false) {
        errorResponse('Failed to save image', 500);
    }
    
    // Get image dimensions
    $imageInfo = getimagesize($filepath);
    $width = $imageInfo[0] ?? 0;
    $height = $imageInfo[1] ?? 0;
    
    successResponse([
        'filename' => $filename,
        'base64' => $base64,
        'mimeType' => $mimeType,
        'size' => strlen($imageData),
        'width' => $width,
        'height' => $height
    ]);
}

/**
 * Handle image deletion
 */
function handleDelete() {
    global $uploadDir;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['filename'])) {
        errorResponse('No filename provided', 400);
    }
    
    $filename = basename($input['filename']); // Prevent directory traversal
    $filepath = $uploadDir . $filename;
    
    if (!file_exists($filepath)) {
        errorResponse('File not found', 404);
    }
    
    if (!unlink($filepath)) {
        errorResponse('Failed to delete file', 500);
    }
    
    successResponse(['deleted' => true]);
}
