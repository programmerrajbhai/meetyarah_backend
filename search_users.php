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

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if ($limit <= 0) $limit = 20;
if ($limit > 50) $limit = 50;

if ($q === '') {
    echo json_encode(['status' => 'success', 'users' => []]);
    exit();
}

$like = "%{$q}%";

$stmt = $conn->prepare("
    SELECT user_id, username, full_name, profile_picture_url
    FROM users
    WHERE user_id != ?
      AND (username LIKE ? OR full_name LIKE ?)
    ORDER BY user_id DESC
    LIMIT ?
");
$stmt->bind_param("issi", $auth_user_id, $like, $like, $limit);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$result = $stmt->get_result();

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

$users = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
        $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
    }
    $users[] = $row;
}

echo json_encode(['status' => 'success', 'users' => $users]);

$stmt->close();
$conn->close();
?>
