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

if ($target_user_id === $auth_user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'You cannot follow yourself']);
    exit();
}

// target user exists?
$stmt_u = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmt_u->bind_param("i", $target_user_id);
$stmt_u->execute();
$r_u = $stmt_u->get_result();
if ($r_u->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Target user not found']);
    exit();
}
$stmt_u->close();

// already following?
$stmt_c = $conn->prepare("SELECT follow_id FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt_c->bind_param("ii", $auth_user_id, $target_user_id);
$stmt_c->execute();
$stmt_c->store_result();

if ($stmt_c->num_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Already following']);
    $stmt_c->close();
    $conn->close();
    exit();
}
$stmt_c->close();

// insert follow
$stmt_i = $conn->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
$stmt_i->bind_param("ii", $auth_user_id, $target_user_id);

if ($stmt_i->execute()) {

    // notification insert (optional)
    $type = "follow";
    $msg = "Someone started following you";
    $stmt_n = $conn->prepare("INSERT INTO notifications (user_id, from_user_id, type, message) VALUES (?, ?, ?, ?)");
    $stmt_n->bind_param("iiss", $target_user_id, $auth_user_id, $type, $msg);
    $stmt_n->execute();
    $stmt_n->close();

    echo json_encode(['status' => 'success', 'message' => 'Followed successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to follow']);
}

$stmt_i->close();
$conn->close();
?>
