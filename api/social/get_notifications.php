<?php
require_once '../../config/db_connect.php';
require_once '../../middleware/auth_middleware.php';

$authenticated_user_id = get_authenticated_user_id($conn);

// নোটিফিকেশন লিস্ট আনা
$sql = "SELECT n.*, u.username, u.profile_picture_url 
        FROM notifications n 
        JOIN users u ON n.from_user_id = u.user_id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $authenticated_user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications
]);
?>