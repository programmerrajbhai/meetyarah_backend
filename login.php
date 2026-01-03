<?php
// ✅ CORS Headers (Flutter Web এর জন্য এটি খুবই গুরুত্বপূর্ণ)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// ✅ Preflight Request Handle (ব্রাউজার প্রথমে OPTIONS রিকোয়েস্ট পাঠায়)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db_connect.php';
header('Content-Type: application/json');

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    if (isset($input['login_identifier']) && isset($input['password'])) {

        $login_identifier = trim($input['login_identifier']);
        $password = trim($input['password']);

        // ব্যবহারকারীর তথ্য খোঁজা
        $stmt = $conn->prepare("SELECT user_id, username, email, full_name, password_hash, profile_picture_url FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $login_identifier, $login_identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // পাসওয়ার্ড ভেরিফিকেশন
            if (password_verify($password, $user['password_hash'])) {
                // ✅ সঠিক পাসওয়ার্ড
                $token = bin2hex(random_bytes(32));

                // টোকেন আপডেট করা
                $stmt_token = $conn->prepare("UPDATE users SET auth_token = ? WHERE user_id = ?");
                $stmt_token->bind_param("si", $token, $user['user_id']);
                $stmt_token->execute();
                $stmt_token->close();

                http_response_code(200);
                $response['status'] = 'success';
                $response['message'] = 'Login successful.';
                
                // সেনসিটিভ ডাটা রিমুভ করা
                unset($user['password_hash']);
                
                $response['user'] = $user;
                $response['token'] = $token;
            } else {
                // ❌ ভুল পাসওয়ার্ড
                http_response_code(401);
                $response['status'] = 'error';
                $response['message'] = 'Invalid username/email or password.';
            }
        } else {
            // ❌ ইউজার পাওয়া যায়নি
            http_response_code(401);
            $response['status'] = 'error';
            $response['message'] = 'Invalid username/email or password.';
        }
        $stmt->close();
    } else {
        http_response_code(400);
        $response['status'] = 'error';
        $response['message'] = 'Login identifier and password are required.';
    }
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>