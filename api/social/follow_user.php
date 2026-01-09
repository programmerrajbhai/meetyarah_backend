<?php
require_once '../../config/db_connect.php';
require_once '../../middleware/auth_middleware.php';

$authenticated_user_id = get_authenticated_user_id($conn);
$input = json_decode(file_get_contents('php://input'), true);
$target_user_id = isset($input['target_user_id']) ? intval($input['target_user_id']) : 0;

if ($target_user_id === 0 || $target_user_id === $authenticated_user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid target user.']);
    exit();
}

// চেক করা হচ্ছে আগে থেকেই ফলো করা আছে কিনা
$check = $conn->prepare("SELECT follow_id FROM follows WHERE follower_id = ? AND following_id = ?");
$check->bind_param("ii", $authenticated_user_id, $target_user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // আনফলো করা
    $stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $message = "Unfollowed successfully.";
} else {
    // ফলো করা
    $stmt = $conn->prepare("INSERT INTO follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
    $message = "Followed successfully.";
}

$stmt->bind_param("ii", $authenticated_user_id, $target_user_id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => $message]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action failed.']);
}
?>