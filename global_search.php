<?php
require_once 'db_connect.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use GET.']);
    exit();
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$user_limit = isset($_GET['user_limit']) ? intval($_GET['user_limit']) : 10;
$post_limit = isset($_GET['post_limit']) ? intval($_GET['post_limit']) : 10;

if ($user_limit <= 0) $user_limit = 10;
if ($post_limit <= 0) $post_limit = 10;
if ($user_limit > 50) $user_limit = 50;
if ($post_limit > 50) $post_limit = 50;

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([
        'status' => 'success',
        'query' => $q,
        'message' => 'Type at least 2 characters',
        'users' => [],
        'posts' => []
    ]);
    exit();
}

$like = "%{$q}%";
$starts = "{$q}%";

// base uploads url (for local filename images)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

/** ---------------- USERS (FIRST) ---------------- */
$users = [];
$stmtU = $conn->prepare("
    SELECT user_id, username, full_name, profile_picture_url
    FROM users
    WHERE username LIKE ? OR full_name LIKE ?
    ORDER BY
      CASE
        WHEN username = ? OR full_name = ? THEN 0
        WHEN username LIKE ? OR full_name LIKE ? THEN 1
        ELSE 2
      END,
      user_id DESC
    LIMIT ?
");

if (!$stmtU) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database prepare error (users)']);
    exit();
}

$stmtU->bind_param("ssssssi", $like, $like, $q, $q, $starts, $starts, $user_limit);

if (!$stmtU->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'User query failed']);
    exit();
}

$resU = $stmtU->get_result();
while ($row = $resU->fetch_assoc()) {
    if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
        $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
    }
    $users[] = $row;
}
$stmtU->close();

/** ---------------- POSTS (SECOND) ---------------- */
$posts = [];
$stmtP = $conn->prepare("
    SELECT post_id, user_id, caption, post_content, image_url, created_at, media_type
    FROM posts
    WHERE caption LIKE ? OR post_content LIKE ?
    ORDER BY
      CASE
        WHEN caption = ? OR post_content = ? THEN 0
        WHEN caption LIKE ? OR post_content LIKE ? THEN 1
        ELSE 2
      END,
      created_at DESC
    LIMIT ?
");

if (!$stmtP) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database prepare error (posts)']);
    exit();
}

$stmtP->bind_param("ssssssi", $like, $like, $q, $q, $starts, $starts, $post_limit);

if (!$stmtP->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Post query failed']);
    exit();
}

$resP = $stmtP->get_result();
while ($row = $resP->fetch_assoc()) {

    // image_url: if stored as filename, make it full url
    if (!empty($row['image_url']) && !filter_var($row['image_url'], FILTER_VALIDATE_URL)) {
        $row['image_url'] = $base_uploads . $row['image_url'];
    }

    // is_video (optional helpful)
    $row['is_video'] = false;
    if (!empty($row['image_url'])) {
        $ext = strtolower(pathinfo(parse_url($row['image_url'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $video_exts = ['mp4','avi','mov','mkv','webm'];
        $row['is_video'] = in_array($ext, $video_exts, true);
    }

    $posts[] = $row;
}
$stmtP->close();

echo json_encode([
    'status' => 'success',
    'query' => $q,
    'counts' => [
        'users' => count($users),
        'posts' => count($posts)
    ],
    'users' => $users,   // ✅ users first
    'posts' => $posts    // ✅ posts second
]);

$conn->close();
?>
