<?php
require_once 'db_connect.php';

header("Content-Type: application/json; charset=UTF-8");

// ✅ CORS (Flutter Web friendly)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'OK']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET.']);
    exit();
}

/**
 * ✅ OPTIONAL AUTH:
 * token থাকলে user_id বের করবে
 * token না থাকলে guest (stories দেখা যাবে)
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

// ✅ Base uploads url
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

// ✅ Get active stories (NOW includes story_text)
$stmt = $conn->prepare("
    SELECT 
        s.story_id, s.user_id, s.media_url, s.media_type, s.story_text, s.created_at, s.expires_at,
        u.username, u.full_name, u.profile_picture_url
    FROM stories s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.expires_at > NOW()
    ORDER BY s.created_at DESC
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database prepare error']);
    exit();
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$result = $stmt->get_result();

$stories = [];
while ($row = $result->fetch_assoc()) {

    // ✅ media_url -> full url (only if it's filename)
    if (!empty($row['media_url']) && !filter_var($row['media_url'], FILTER_VALIDATE_URL)) {
        $row['media_url'] = $base_uploads . $row['media_url'];
    }

    // ✅ profile_picture_url -> full url (only if it's filename)
    if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
        $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
    }

    // ✅ Flutter compatibility aliases
    $row['image_url'] = $row['media_url'];       // old UI key
    $row['story_image'] = $row['media_url'];     // optional alias
    $row['text'] = $row['story_text'] ?? "";     // ✅ text alias

    // ✅ helper flags
    $row['is_video'] = ($row['media_type'] === 'video');
    $row['is_text']  = ($row['media_type'] === 'text');

    $stories[] = $row;
}

echo json_encode([
    'status' => 'success',
    'is_guest' => ($auth_user_id === null),
    'stories' => $stories
]);

$stmt->close();
$conn->close();
?>
