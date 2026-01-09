<?php
include 'db_connect.php';
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

    if (isset($input['post_id']) && isset($input['user_id']) && isset($input['comment_text'])) {

        $post_id = $input['post_id'];
        $user_id = $input['user_id'];
        $comment_text = $input['comment_text'];
        
        // প্যারেন্ট কমেন্ট আইডি (রিপ্লাই এর জন্য), না থাকলে NULL
        $parent_comment_id = isset($input['parent_comment_id']) ? $input['parent_comment_id'] : NULL;

        // কুয়েরি প্রিপেয়ার করা
        $sql = "INSERT INTO comments (post_id, user_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // বাইন্ডিং: iisi (integer, integer, string, integer)
            $stmt->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_comment_id);

            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Comment added successfully.';
                $response['comment_id'] = $stmt->insert_id;
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Database execute error: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Statement preparation failed: ' . $conn->error;
        }

    } else {
        $response['status'] = 'error';
        $response['message'] = 'Required fields (post_id, user_id, comment_text) are missing.';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>