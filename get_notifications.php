<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

$auth_user_id = get_authenticated_user_id($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET.']);
    exit();
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 100) $limit = 100;

$stmt = $conn->prepare("
    SELECT notification_id, from_user_id, type, ref_id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->bind_param("ii", $auth_user_id, $limit);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$result = $stmt->get_result();
$list = [];
while ($row = $result->fetch_assoc()) {
    $row['is_read'] = (int)$row['is_read'] === 1;
    $list[] = $row;
}
$stmt->close();

// optional mark read
$mark = isset($_GET['mark_read']) ? intval($_GET['mark_read']) : 0;
if ($mark === 1) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = " . intval($auth_user_id));
}

echo json_encode(['status' => 'success', 'notifications' => $list]);
$conn->close();
?>
