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

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'post_id is required']);
    exit();
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_uploads = "$protocol://$host$script_dir/uploads/";

$stmt = $conn->prepare("
    SELECT 
        p.post_id, p.user_id, p.post_content, p.image_url, p.created_at,
        u.username, u.full_name, u.profile_picture_url,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE comments.post_id = p.post_id) AS comment_count,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = p.post_id AND likes.user_id = ?) AS is_liked
    FROM posts p
    JOIN users u ON u.user_id = p.user_id
    WHERE p.post_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $auth_user_id, $post_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit();
}

$res = $stmt->get_result();
if ($res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Post not found']);
    exit();
}

$row = $res->fetch_assoc();
$row['is_liked'] = ((int)$row['is_liked'] > 0);
$row['like_count'] = (int)$row['like_count'];
$row['comment_count'] = (int)$row['comment_count'];

if (!empty($row['image_url']) && !filter_var($row['image_url'], FILTER_VALIDATE_URL)) {
    $row['image_url'] = $base_uploads . $row['image_url'];
}

if (!empty($row['profile_picture_url']) && !filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
    $row['profile_picture_url'] = $base_uploads . $row['profile_picture_url'];
}

// is_video flag (get_all_posts এর মতো)
if (!empty($row['image_url'])) {
    $ext = strtolower(pathinfo($row['image_url'], PATHINFO_EXTENSION));
    $video_exts = ['mp4','avi','mov','mkv','webm'];
    $row['is_video'] = in_array($ext, $video_exts);
} else {
    $row['is_video'] = false;
}

echo json_encode(['status' => 'success', 'post' => $row]);

$stmt->close();
$conn->close();
?>
