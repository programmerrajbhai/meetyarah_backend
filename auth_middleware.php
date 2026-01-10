<?php
// Database schema প্রথমে:
// ALTER TABLE users ADD COLUMN token_expires_at TIMESTAMP DEFAULT NULL;

require_once 'db_connect.php';

function get_authenticated_user_id($conn) {
    // Authorization header extract করা
    $auth_header = null;
    
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    
    if (!$auth_header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    // Token extract
    if (!$auth_header) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
        exit();
    }
    
    $token = trim($auth_header);
    if (preg_match('/Bearer\s+(\S+)/i', $token, $matches)) {
        $token = $matches[1];
    }
    
    // Token validation with expiration
    $sql = "SELECT user_id, token_expires_at FROM users WHERE auth_token = ? AND (token_expires_at IS NULL OR token_expires_at > NOW())";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        exit();
    }
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        // Token এখনও valid আছে
        $stmt->close();
        return (int)$user['user_id'];
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
        exit();
    }
}
?>