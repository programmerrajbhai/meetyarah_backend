<?php
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ১. ইনপুট নেওয়া (User ID এবং Pagination)
$current_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // প্রতি পেজে ১০টি পোস্ট

// পেজ ১ এর নিচে হতে পারবে না
if ($page < 1) $page = 1;

// OFFSET ক্যালকুলেশন
$offset = ($page - 1) * $limit;

$response = array();

// ২. ডাইনামিক ইমেজ বেস URL তৈরি
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['PHP_SELF']);
$script_dir = rtrim($script_dir, '/');
$base_image_url = "$protocol://$host$script_dir/uploads/";

// ৩. অপটিমাইজড SQL Query (LIMIT ও OFFSET সহ)
$sql = "SELECT 
            posts.post_id, posts.user_id, posts.post_content, posts.image_url, posts.created_at,
            users.username, users.full_name, users.profile_picture_url,
            
            -- লাইক দিয়েছে কি না?
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = $current_user_id) as is_liked,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id AND likes.user_id = ?) as is_liked,
            (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) as total_likes,
            (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.post_id) as total_comments

        FROM posts 
        JOIN users ON posts.user_id = users.user_id 
        ORDER BY posts.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // প্যারামিটার বাইন্ডিং
    $stmt->bind_param("iii", $current_user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $response['status'] = 'success';
        $response['posts'] = array();

        while ($row = $result->fetch_assoc()) {
            $row['is_liked'] = ($row['is_liked'] > 0);
            $row['like_count'] = intval($row['total_likes']);
            $row['comment_count'] = intval($row['total_comments']);
            
            unset($row['total_likes']);
            unset($row['total_comments']);

            // ইমেজ লিংক জেনারেশন
            if (!empty($row['image_url'])) {
                if (!filter_var($row['image_url'], FILTER_VALIDATE_URL)) {
                    $row['image_url'] = $base_image_url . $row['image_url'];
                }
                
                // ভিডিও নাকি ইমেজ চেক করার ফ্ল্যাগ
                $ext = pathinfo($row['image_url'], PATHINFO_EXTENSION);
                $video_exts = ['mp4', 'avi', 'mov', 'mkv', 'webm'];
                $row['is_video'] = in_array(strtolower($ext), $video_exts);
            } else {
                $row['is_video'] = false;
            }
            
            // প্রোফাইল পিকচার লিংক
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
        $response['message'] = "No more posts found";
    }
    $stmt->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Query failed: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>