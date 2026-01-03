<?php
include 'db_connect.php'; // ডাটাবেস কানেকশন

$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // একটি কমেন্টের জন্য এই ৩টি ফিল্ড আবশ্যক
    if (isset($input['post_id']) && isset($input['user_id']) && isset($input['comment_text'])) {

        $post_id = $input['post_id'];
        $user_id = $input['user_id'];
        $comment_text = $input['comment_text'];

        // রিপ্লাইয়ের জন্য: যদি 'parent_comment_id' পাঠানো হয়, তবে সেটি নেবে, না হলে NULL
        $parent_comment_id = $input['parent_comment_id'] ?? NULL;

        // ডাটাবেসে নতুন কমেন্ট ইনসার্ট করি
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)");
        
        // 'i' = integer, 's' = string
        // $parent_comment_id যদি NULL হয়, bind_param 'i' তে সমস্যা করতে পারে, তাই is_null চেক করা ভালো
        if (is_null($parent_comment_id)) {
            $stmt->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_comment_id);
            // দ্রষ্টব্য: উপরের লাইনে iisi-এর বদলে iiss বা iis(null) ব্যবহার করা লাগতে পারে যদি NULL bind-এ সমস্যা হয়।
            // তবে আধুনিক PHP/MySQLi এটি হ্যান্ডেল করতে পারে।
        } else {
             $stmt->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_comment_id);
        }

        // একটি সহজ এবং নিরাপদ উপায়:
        $stmt_insert = $conn->prepare("INSERT INTO comments (post_id, user_id, comment_text, parent_comment_id) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("iisi", $post_id, $user_id, $comment_text, $parent_comment_id);


        if ($stmt_insert->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Comment added successfully.';
            $response['comment_id'] = $stmt_insert->insert_id; // নতুন কমেন্টের ID
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to add comment: ' . $stmt_insert->error;
        }
        $stmt_insert->close();

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