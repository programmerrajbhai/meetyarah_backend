<?php
include 'db_connect.php'; // ডাটাবেস কানেকশন

$response = array();

// আমরা আশা করবো ইউজার URL-এ post_id পাঠাবে (e.g., get_comments.php?post_id=1)
// এটি GET রিকোয়েস্ট হবে
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    // চেক করি URL-এ post_id আছে কি না
    if (isset($_GET['post_id'])) {
        $post_id = $_GET['post_id'];

        // SQL কোয়েরি: কমেন্টের সাথে ইউজার টেবিল JOIN করে কমেন্টারের তথ্যও নেবো
        $stmt = $conn->prepare("
            SELECT 
                c.comment_id,
                c.comment_text,
                c.parent_comment_id,
                c.created_at,
                u.user_id,
                u.username,
                u.full_name,
                u.profile_picture_url
            FROM 
                comments c
            JOIN 
                users u ON c.user_id = u.user_id
            WHERE 
                c.post_id = ?
            ORDER BY 
                c.created_at ASC 
        "); // পুরনো কমেন্ট আগে দেখাবে

        $stmt->bind_param("i", $post_id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $comments_list = array();

            while ($row = $result->fetch_assoc()) {
                $comments_list[] = $row;
            }

            $response['status'] = 'success';
            $response['comments'] = $comments_list;

        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to fetch comments.';
        }
        $stmt->close();

    } else {
        $response['status'] = 'error';
        $response['message'] = 'Post ID is required. (e.g., /get_comments.php?post_id=1)';
    }

} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Please use GET.';
}

$conn->close();
echo json_encode($response);
?>