<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


include 'db_connect.php';
// JSON রেসপন্স হেডার নিশ্চিত করা ভালো
header('Content-Type: application/json');

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // অ্যাপ থেকে পাঠানো JSON ডেটা গ্রহণ করি
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // JSON-কে PHP অ্যারে-তে রূপান্তর করি

    // প্রয়োজনীয় ফিল্ডগুলো চেক করি
    if (isset($input['username']) && isset($input['email']) && isset($input['password']) && isset($input['full_name'])) {

        $username = trim($input['username']);
        $email = trim($input['email']);
        $password = trim($input['password']);
        $full_name = trim($input['full_name']);

        // নিরাপত্তার জন্য পাসওয়ার্ড হ্যাশ করি
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // ১. চেক করি ইউজারনেম বা ইমেইল আগে থেকেই আছে কি না
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            // যদি ইউজারনেম বা ইমেইল পাওয়া যায়
            $response['status'] = 'error';
            $response['message'] = 'Username or Email already exists.';
        } else {
            // ২. নতুন ইউজার ডাটাবেসে ইনসার্ট করি
            $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $username, $email, $password_hash, $full_name);

            if ($stmt_insert->execute()) {
                // সফলভাবে রেজিস্টার হলে
                http_response_code(200); // Success Code
                $response['status'] = 'success';
                $response['message'] = 'User registered successfully.';
            } else {
                // ইনসার্ট করতে সমস্যা হলে
                http_response_code(500); // Server Error Code
                $response['status'] = 'error';
                $response['message'] = 'Registration failed. Please try again.';
            }
            $stmt_insert->close();
        }
        $stmt_check->close();

    } else {
        // যদি সবগুলো ফিল্ড না পাঠানো হয়
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Required fields are missing.';
    }
} else {
    // যদি POST রিকোয়েস্ট না আসে
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>