<?php
// ১. ডাটাবেস কানেকশন (এখান থেকেই সব হেডার পাবে)
require_once 'db_connect.php';

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
                // টোকেন জেনারেট
                try {
                    $token = bin2hex(random_bytes(32));
                } catch (Exception $e) {
                    $token = bin2hex(openssl_random_pseudo_bytes(32));
                }

                // টোকেন আপডেট করা
                $stmt_token = $conn->prepare("UPDATE users SET auth_token = ? WHERE user_id = ?");
                $stmt_token->bind_param("si", $token, $user['user_id']);
                $stmt_token->execute();
                $stmt_token->close();

                http_response_code(200);
                
                // সেনসিটিভ ডাটা রিমুভ
                unset($user['password_hash']);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login successful.',
                    'user' => $user,
                    'token' => $token
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Invalid password.']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        }
        $stmt->close();
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email and password required.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}

$conn->close();
?>