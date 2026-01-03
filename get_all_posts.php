<?php
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ১. অ্যাপ থেকে আসা user_id রিসিভ করা
$current_user_id = 0;
if (isset($_GET['user_id'])) {
    $current_user_id = intval($_GET['user_id']);
}

$response = array();

// ২. SQL Query (এখানে লাইক এবং কমেন্ট সরাসরি টেবিল থেকে গুনে আনা হচ্ছে)
$sql = "SELECT 
            posts.*, 
            users.username, 
            users.full_name, 
            users.profile_picture_url,
            
            -- ১. ইউজার লাইক দিয়েছে কি না? (1 = Yes, 0 = No)
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = $current_user_id) as is_liked,
            
            -- ২. মোট লাইক সংখ্যা (সরাসরি likes টেবিল থেকে গণনা)
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) as total_likes,
            
            -- ৩. মোট কমেন্ট সংখ্যা (সরাসরি comments টেবিল থেকে গণনা)
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.post_id) as total_comments

        FROM posts 
        JOIN users ON posts.user_id = users.user_id 
        ORDER BY posts.created_at DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $response['status'] = 'success';
    $response['posts'] = array();

    while ($row = $result->fetch_assoc()) {
        // is_liked কনভার্ট করা
        $row['is_liked'] = ($row['is_liked'] > 0) ? true : false;
        
        // মোট লাইক এবং কমেন্ট সংখ্যা JSON এ সেট করা
        // লক্ষ্য করুন: আমরা 'like_count' এবং 'comment_count' নামেই অ্যাপে পাঠাচ্ছি
        $row['like_count'] = intval($row['total_likes']);
        $row['comment_count'] = intval($row['total_comments']);
        
        array_push($response['posts'], $row);
    }
} else {
    $response['status'] = 'success'; 
    $response['posts'] = []; 
    $response['message'] = "No posts found";
}

$conn->close();
echo json_encode($response);
?>