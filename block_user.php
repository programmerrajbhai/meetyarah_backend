<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit();
}

$auth_user_id = get_authenticated_user_id($conn);

$input = json_decode(file_get_contents("php://input"), true);
$target_id = intval($input['target_user_id'] ?? 0);

if ($target_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'target_user_id is required']);
    exit();
}

if ($target_id === $auth_user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'You cannot block yourself']);
    exit();
}

// ✅ Insert ignore (duplicate হলে fail না)
$stmt = $conn->prepare("
    INSERT IGNORE INTO user_blocks (blocker_user_id, blocked_user_id)
    VALUES (?, ?)
");
$stmt->bind_param("ii", $auth_user_id, $target_id);

if ($stmt->execute()) {
    // Optional: block করলে follow remove করে দিতে চাইলে:
    // $conn->query("DELETE FROM followers WHERE follower_id=$auth_user_id AND following_id=$target_id");
    // $conn->query("DELETE FROM followers WHERE follower_id=$target_id AND following_id=$auth_user_id");

    echo json_encode([
        'status' => 'success',
        'message' => 'User blocked',
        'blocked_user_id' => $target_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
