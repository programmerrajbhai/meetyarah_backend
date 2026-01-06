<?php
include 'db_connect.php'; // ডাটাবেস কানেকশন

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// ✅ Preflight handling
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // লাইক দেওয়ার জন্য 'user_id' এবং 'post_id' দুটোই লাগবে
    if (isset($input['user_id']) && isset($input['post_id'])) {

        $user_id = $input['user_id'];
        $post_id = $input['post_id'];

        // ১. প্রথমে চেক করি ইউজার ইতোমধ্যে এই পোস্টে লাইক দিয়েছেন কি না
        $stmt_check = $conn->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt_check->bind_param("ii", $user_id, $post_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            // --- লাইক ইতোমধ্যে দেওয়া আছে, এখন "আনলাইক" (Unlike) করতে হবে ---
            
            $stmt_delete = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
            $stmt_delete->bind_param("ii", $user_id, $post_id);

            if ($stmt_delete->execute()) {
                $response['status'] = 'success';
                $response['action'] = 'unliked'; // অ্যাপকে জানাই যে এটি আনলাইক হয়েছে
                $response['message'] = 'Post unliked successfully.';
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to unlike post.';
            }
            $stmt_delete->close();

        } else {
            // --- লাইক দেওয়া নেই, এখন "লাইক" (Like) করতে হবে ---
            
            $stmt_insert = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $user_id, $post_id);

            if ($stmt_insert->execute()) {
                $response['status'] = 'success';
                $response['action'] = 'liked'; // অ্যাপকে জানাই যে এটি লাইক হয়েছে
                $response['message'] = 'Post liked successfully.';
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Failed to like post.';
            }
            $stmt_insert->close();
        }
        $stmt_check->close();

    } else {
        $response['status'] = 'error';
        $response['message'] = 'Required fields (user_id, post_id) are missing.';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>