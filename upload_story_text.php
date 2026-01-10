<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

// ✅ CORS (Flutter Web friendly)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'OK']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit();
}

$auth_user_id = get_authenticated_user_id($conn);

// ✅ JSON body
$input = json_decode(file_get_contents("php://input"), true);
$text = trim($input['text'] ?? '');

if ($text === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Text is required']);
    exit();
}

// ✅ Insert text story (expire 24h)
$stmt = $conn->prepare("
    INSERT INTO stories (user_id, media_url, media_type, story_text, expires_at)
    VALUES (?, NULL, 'text', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
");
$stmt->bind_param("is", $auth_user_id, $text);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Text story uploaded',
        'story_id' => $stmt->insert_id,
        'media_type' => 'text',
        'text' => $text
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
