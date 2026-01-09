<?php
require_once '../../config/db_connect.php';
require_once '../../middleware/auth_middleware.php';

// টোকেন যাচাই করে ইউজার আইডি নেওয়া
$authenticated_user_id = get_authenticated_user_id($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit();
}

$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : null;
$profile_picture = null;

// প্রোফাইল পিকচার আপলোড লজিক
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../uploads/';
    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $profile_picture = 'profile_' . $authenticated_user_id . '_' . time() . '.' . $ext;
    
    move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $profile_picture);
}

// ডাটাবেস আপডেট
if ($full_name && $profile_picture) {
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, profile_picture_url = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $full_name, $profile_picture, $authenticated_user_id);
} elseif ($full_name) {
    $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
    $stmt->bind_param("si", $full_name, $authenticated_user_id);
} elseif ($profile_picture) {
    $stmt = $conn->prepare("UPDATE users SET profile_picture_url = ? WHERE user_id = ?");
    $stmt->bind_param("si", $profile_picture, $authenticated_user_id);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nothing to update.']);
    exit();
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Update failed.']);
}
?>