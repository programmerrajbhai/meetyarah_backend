<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

ini_set('display_errors', 0);
error_reporting(E_ALL);

$authenticated_user_id = get_authenticated_user_id($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit();
}

$post_text = isset($_POST['caption']) ? trim($_POST['caption']) : '';

// আপলোড ফোল্ডার কনফিগারেশন
$uploadFileDir = __DIR__ . '/uploads/';
if (!is_dir($uploadFileDir)) {
    if (!mkdir($uploadFileDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create uploads directory.']);
        exit();
    }
}

$newFileName = null;

// --- A. IMAGE UPLOAD LOGIC ---
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    
    $allowedImageExts = array('jpg', 'gif', 'png', 'jpeg', 'webp');
    $maxImageSize = 10 * 1024 * 1024; // 10 MB

    $fileNameOriginal = $_FILES['image']['name'];
    $fileSize = $_FILES['image']['size'];
    $fileTmpPath = $_FILES['image']['tmp_name'];

    // সাইজ চেক
    if ($fileSize > $maxImageSize) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Image too large. Max 10 MB.']);
        exit();
    }

    // এক্সটেনশন চেক
    $ext = strtolower(pathinfo($fileNameOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedImageExts)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid image format. Allowed: JPG, PNG, WEBP.']);
        exit();
    }

    // ইউনিক নাম
    $newFileName = uniqid('img_', true) . '_' . $authenticated_user_id . '.' . $ext;
    
    if (!move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save image.']);
        exit();
    }

// --- B. VIDEO UPLOAD LOGIC ---
} elseif (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {

    $allowedVideoExts = array('mp4', 'avi', 'mov', 'mkv', 'webm');
    $maxVideoSize = 100 * 1024 * 1024; // 100 MB

    $fileNameOriginal = $_FILES['video']['name'];
    $fileSize = $_FILES['video']['size'];
    $fileTmpPath = $_FILES['video']['tmp_name'];

    // সাইজ চেক
    if ($fileSize > $maxVideoSize) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Video too large. Max 100 MB.']);
        exit();
    }

    // এক্সটেনশন চেক
    $ext = strtolower(pathinfo($fileNameOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedVideoExts)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid video format. Allowed: MP4, AVI, MOV.']);
        exit();
    }

    // ইউনিক নাম
    $newFileName = uniqid('vid_', true) . '_' . $authenticated_user_id . '.' . $ext;

    if (!move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save video.']);
        exit();
    }
}

// --- DATABASE INSERT ---
if (empty($post_text) && $newFileName === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Post cannot be empty.']);
    exit();
}

$sql = "INSERT INTO posts (user_id, post_content, image_url, created_at) VALUES (?, ?, ?, NOW())";
// নোট: আমাদের ডাটাবেস কলামের নাম 'image_url' হলেও আমরা সেখানে ভিডিও ফাইলের নামও রাখব।
// ফ্রন্টএন্ডে এক্সটেনশন (.mp4/.jpg) চেক করে ডিসাইড করব কোনটা ভিডিও প্লেয়ারে দেখাবো আর কোনটা ইমেজে।

$stmt = $conn->prepare($sql);

if ($newFileName !== null) {
    $stmt->bind_param("iss", $authenticated_user_id, $post_text, $newFileName);
} else {
    // ফাইল নেই, শুধু টেক্সট
    // তবে কুয়েরি যেহেতু image_url চাচ্ছে, তাই NULL পাস করতে হবে বা কুয়েরি ডাইনামিক করতে হবে।
    // সহজ করার জন্য উপরে একই কুয়েরি রেখেছি, এখানে নাল বাইন্ড করছি।
    $nullVal = null;
    $stmt->bind_param("iss", $authenticated_user_id, $post_text, $nullVal);
}

if ($stmt->execute()) {
    http_response_code(201);
    
    // লিংক জেনারেশন
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $full_url = $newFileName ? "$protocol://$host$script_dir/uploads/$newFileName" : null;

    echo json_encode([
        'status' => 'success',
        'message' => 'Post created successfully',
        'post_id' => $stmt->insert_id,
        'media_url' => $full_url
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>