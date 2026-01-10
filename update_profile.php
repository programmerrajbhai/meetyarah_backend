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

$username = isset($_POST['username']) ? trim($_POST['username']) : null;
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : null;
$bio = isset($_POST['bio']) ? trim($_POST['bio']) : null;

// uploads dir
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$newProfileFile = null;

// profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid image format (jpg,png,webp)']);
        exit();
    }

    $newProfileFile = uniqid('pp_', true) . '_' . $auth_user_id . '.' . $ext;
    if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $newProfileFile)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save profile picture']);
        exit();
    }
}

// dynamic update query
$fields = [];
$params = [];
$types = "";

if ($username !== null && $username !== "") { $fields[] = "username = ?"; $params[] = $username; $types .= "s"; }
if ($full_name !== null) { $fields[] = "full_name = ?"; $params[] = $full_name; $types .= "s"; }
if ($bio !== null) { $fields[] = "bio = ?"; $params[] = $bio; $types .= "s"; }
if ($newProfileFile !== null) { $fields[] = "profile_picture_url = ?"; $params[] = $newProfileFile; $types .= "s"; }

if (count($fields) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No fields to update']);
    exit();
}

$sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?";
$params[] = $auth_user_id;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $base_uploads = "$protocol://$host$script_dir/uploads/";

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated',
        'profile_picture_url' => $newProfileFile ? ($base_uploads . $newProfileFile) : null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
