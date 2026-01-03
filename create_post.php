<?php
include 'db_connect.php';

// হেডার ফিক্স (যদি লাগে)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // চেক করছি user_id এবং post_content আসছে কি না
    if (isset($input['user_id']) && isset($input['post_content'])) {

        $user_id = $input['user_id'];
        $post_content = $input['post_content'];
        $image_url = $input['image_url'] ?? NULL; // ইমেজ না থাকলে নাল হবে

        // সরাসরি ডাটাবেসে ইনসার্ট
        $stmt = $conn->prepare("INSERT INTO posts (user_id, post_content, image_url) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $post_content, $image_url);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Post created successfully.';
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to create post.';
        }
        $stmt->close();

    } else {
        $response['status'] = 'error';
        $response['message'] = 'Required fields (user_id or post_content) are missing.';
    }

} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>