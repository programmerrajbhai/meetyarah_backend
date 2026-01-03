<?php
function get_authenticated_user_id($conn) {
    $auth_header = null;

    // ১. সরাসরি Apache Request Headers চেক করি
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        // ছোট হাতের 'authorization' বা বড় হাতের 'Authorization' দুটোই চেক করি
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $auth_header = $headers['authorization'];
        }
    }

    // ২. যদি না পাই, তবে SERVER ভেরিয়েবল চেক করি (সাধারণত .htaccess থাকলে এখানে আসে)
    if (!$auth_header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    }

    // ৩. কিছু সার্ভারে এটি REDIRECT_ প্রিফিক্স সহ আসে
    if (!$auth_header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // --- টোকেন পাওয়া না গেলে এরর ---
    if (!$auth_header) {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing.']);
        exit();
    }
    
    // ৪. টোকেন ফরম্যাট চেক (Bearer <token>)
    if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Invalid token format.']);
        exit();
    }
    
    $token = $matches[1];

    // ৫. ডাটাবেস চেক
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE auth_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        return (int)$user['user_id']; 
    } else {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid token.']);
        exit(); 
    }
}
?>