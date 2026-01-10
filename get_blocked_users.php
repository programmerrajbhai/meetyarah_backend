<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET.']);
    exit();
}

$auth_user_id = get_authenticated_user_id($conn);

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 200) $limit = 200;

$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.full_name, u.profile_picture_url, b.created_at
    FROM user_blocks b
    JOIN users u ON u.user_id = b.blocked_user_id
    WHERE b.blocker_user_id = ?
    ORDER BY b.created_at DESC
    LIMIT ?
");
$stmt->bind_param("ii", $auth_user_id, $limit);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$res = $stmt->get_result();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

$blocked = [];
while ($row = $res->fetch_assoc()) {
    if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
        $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
    }
    $blocked[] = $row;
}

echo json_encode(['status' => 'success', 'blocked_users' => $blocked]);

$stmt->close();
$conn->close();
?>
