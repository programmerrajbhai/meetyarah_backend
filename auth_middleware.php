<?php
// ডিবাগিং এর জন্য এরর রিপোর্টিং অন করা হলো (ডেভেলপমেন্ট শেষে বন্ধ করবেন)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function get_authenticated_user_id($conn) {
    $auth_header = null;

    // ১. হেডার চেক
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth_header = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $auth_header = $headers['authorization'];
        }
    }

    // ২. সার্ভার ভেরিয়েবল চেক
    if (!$auth_header && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!$auth_header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // ৩. টোকেন মিসিং চেক
    if (!$auth_header) {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing.']);
        exit();
    }

    // ৪. টোকেন এক্সট্রাক্ট (Flexible Logic)
    $token = trim($auth_header);
    if (preg_match('/Bearer\s+(\S+)/i', $token, $matches)) {
        $token = $matches[1];
    }

    // ৫. ডাটাবেস চেক (এখানেই ৫০০ এরর হচ্ছে সম্ভবত)
    // আগে চেক করি users টেবিল বা auth_token কলাম ঠিক আছে কি না
    $sql = "SELECT user_id FROM users WHERE auth_token = ?";
    $stmt = $conn->prepare($sql);

    // যদি কুয়েরি ভুল হয় (যেমন টেবিল নেই), তবে এরর দেখাবে
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database Query Failed! Check table/column name.',
            'debug_error' => $conn->error // আসল এররটা এখানে দেখা যাবে
        ]);
        exit();
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        return (int)$user['user_id']; 
    } else {
        http_response_code(401); 
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid token or User not found.']);
        exit(); 
    }
}
?>