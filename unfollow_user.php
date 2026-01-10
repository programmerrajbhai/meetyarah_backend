<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_user_id = get_authenticated_user_id($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = isset($input['target_user_id']) ? intval($input['target_user_id']) : 0;

if ($target_user_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'target_user_id is required']);
    exit();
}

$stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->bind_param("ii", $auth_user_id, $target_user_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => ($stmt->affected_rows > 0) ? 'Unfollowed successfully' : 'You were not following'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to unfollow']);
}

$stmt->close();
$conn->close();
?>
