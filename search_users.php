<?php
require_once 'db_connect.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET.']);
    exit();
}

/**
 * ✅ OPTIONAL AUTH (token থাকলে শুধু user_id নিবে, না থাকলে guest)
 */
$auth_user_id = null;

$auth_header = null;
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
}
if (!$auth_header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
}
if (!$auth_header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

if ($auth_header) {
    $token = trim($auth_header);
    if (preg_match('/Bearer\s+(\S+)/i', $token, $m)) $token = $m[1];

    $stmtT = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE auth_token = ?
          AND (token_expires_at IS NULL OR token_expires_at > NOW())
        LIMIT 1
    ");
    if ($stmtT) {
        $stmtT->bind_param("s", $token);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        if ($resT && $resT->num_rows === 1) {
            $rowT = $resT->fetch_assoc();
            $auth_user_id = (int)$rowT['user_id'];
        }
        $stmtT->close();
    }
}

// Params
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if ($limit <= 0) $limit = 20;
if ($limit > 50) $limit = 50;

// ✅ minimum length (optional but recommended)
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([
        'status' => 'success',
        'is_guest' => ($auth_user_id === null),
        'users' => []
    ]);
    exit();
}

$like = "%{$q}%";
$starts = "{$q}%";

// Base uploads url
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

/**
 * ✅ Ranking:
 * 0 = exact match
 * 1 = startswith
 * 2 = contains
 */
if ($auth_user_id !== null) {
    $stmt = $conn->prepare("
        SELECT user_id, username, full_name, profile_picture_url
        FROM users
        WHERE user_id != ?
          AND (username LIKE ? OR full_name LIKE ?)
        ORDER BY
          CASE
            WHEN username = ? OR full_name = ? THEN 0
            WHEN username LIKE ? OR full_name LIKE ? THEN 1
            ELSE 2
          END,
          user_id DESC
        LIMIT ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error']);
        exit();
    }

    $stmt->bind_param("issssssi",
        $auth_user_id,
        $like, $like,
        $q, $q,
        $starts, $starts,
        $limit
    );
} else {
    $stmt = $conn->prepare("
        SELECT user_id, username, full_name, profile_picture_url
        FROM users
        WHERE (username LIKE ? OR full_name LIKE ?)
        ORDER BY
          CASE
            WHEN username = ? OR full_name = ? THEN 0
            WHEN username LIKE ? OR full_name LIKE ? THEN 1
            ELSE 2
          END,
          user_id DESC
        LIMIT ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error']);
        exit();
    }

    $stmt->bind_param("ssssssi",
        $like, $like,
        $q, $q,
        $starts, $starts,
        $limit
    );
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
        $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
    }
    $users[] = $row;
}

echo json_encode([
    'status' => 'success',
    'is_guest' => ($auth_user_id === null),
    'query' => $q,
    'count' => count($users),
    'users' => $users
]);

$stmt->close();
$conn->close();
?>
