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

// ২. ডাইনামিক ইমেজ বেস URL তৈরি (এটি অটোমেটিক http/https এবং IP/Domain ধরে নিবে)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
// বর্তমান ফোল্ডার (api) থেকে uploads ফোল্ডারের পাথ
$script_dir = dirname($_SERVER['PHP_SELF']);
// স্লাশ হ্যান্ডলিং (যাতে ডাবল স্লাশ না হয়)
$script_dir = rtrim($script_dir, '/');
$base_image_url = "$protocol://$host$script_dir/uploads/";

// ৩. SQL Query
$sql = "SELECT 
            posts.*, 
            users.username, 
            users.full_name, 
            users.profile_picture_url,
            
            -- লাইক দিয়েছে কি না?
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = $current_user_id) as is_liked,
            
            -- মোট লাইক
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) as total_likes,
            
            -- মোট কমেন্ট
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.post_id) as total_comments

        FROM posts 
        JOIN users ON posts.user_id = users.user_id 
        ORDER BY posts.created_at DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $response['status'] = 'success';
    $response['posts'] = array();

    while ($row = $result->fetch_assoc()) {
        // ১. is_liked বুলিয়ান করা
        $row['is_liked'] = ($row['is_liked'] > 0) ? true : false;
        
        // ২. সংখ্যা ইন্টিজারে নেওয়া
        $row['like_count'] = intval($row['total_likes']);
        $row['comment_count'] = intval($row['total_comments']);
        
        // ৩. ইমেজের পূর্ণাঙ্গ লিংক তৈরি করা (সবচেয়ে গুরুত্বপূর্ণ ফিক্স)
        if (!empty($row['image_url'])) {
            // যদি ডাটাবেসে শুধু নাম থাকে (যেমন: post.jpg), তবে সামনে পাথ যোগ হবে
            if (!filter_var($row['image_url'], FILTER_VALIDATE_URL)) {
                $row['image_url'] = $base_image_url . $row['image_url'];
            }
        }
        
        // ৪. প্রোফাইল পিকচারের জন্যও একই কাজ করা উচিত (যদি সেখানেও শুধু নাম থাকে)
        if (!empty($row['profile_picture_url'])) {
             if (!filter_var($row['profile_picture_url'], FILTER_VALIDATE_URL)) {
                $row['profile_picture_url'] = $base_image_url . $row['profile_picture_url'];
            }
        }
        
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