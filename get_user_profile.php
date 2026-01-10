<?php
// ১. ডাটাবেস এবং অথেন্টিকেশন ফাইল ইনক্লুড করা
require_once 'db_connect.php';
require_once 'auth_middleware.php'; // 🔥 এই ফাইলটি অবশ্যই থাকতে হবে ইউজার আইডি পাওয়ার জন্য

header("Content-Type: application/json; charset=UTF-8");

$response = array();

// ২. মেথড চেক করা
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    // ৩. অথেনটিকেটেড ইউজারের আইডি বের করা (যে প্রোফাইল দেখছে)
    $auth_user_id = get_authenticated_user_id($conn);

    if (isset($_GET['user_id'])) {
        $target_user_id = intval($_GET['user_id']); // যার প্রোফাইল দেখা হচ্ছে

        if ($target_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid User ID']);
            exit();
        }

        // --- ধাপ ১: ইউজারের প্রোফাইল, ফলো স্ট্যাটাস এবং কাউন্ট আনা ---
        // আমরা সাব-কুয়েরি (Sub-query) ব্যবহার করছি যাতে এক কলেই সব ডাটা পাওয়া যায়
        $sql = "SELECT 
                    u.user_id, 
                    u.username, 
                    u.full_name, 
                    u.profile_picture_url, 
                    u.bio, 
                    u.created_at,
                    
                    -- ফলোয়ার সংখ্যা (কতজন তাকে ফলো করে)
                    (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) as followers_count,
                    
                    -- ফলোয়িং সংখ্যা (সে কতজনকে ফলো করে)
                    (SELECT COUNT(*) FROM follows WHERE follower_id = u.user_id) as following_count,
                    
                    -- 🔥 আমি কি তাকে ফলো করি? (1 = Yes, 0 = No)
                    (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = u.user_id) as is_following,

                    -- 🔥 সে কি আমাকে ফলো করে? (Friends লজিকের জন্য)
                    (SELECT COUNT(*) FROM follows WHERE follower_id = u.user_id AND following_id = ?) as is_following_viewer

                FROM users u 
                WHERE u.user_id = ?";

        $stmt_user = $conn->prepare($sql);
        
        // প্যারামিটার বাইন্ডিং: auth_id (আমার), auth_id (আমার - রিভার্স চেকের জন্য), target_id (তার)
        $stmt_user->bind_param("iii", $auth_user_id, $auth_user_id, $target_user_id);
        
        if ($stmt_user->execute()) {
            $result_user = $stmt_user->get_result();

            if ($result_user->num_rows == 1) {
                
                $response['status'] = 'success';
                $profile_data = $result_user->fetch_assoc();
                
                // ডাটা টাইপ ঠিক করা (PHP অনেক সময় স্ট্রিং রিটার্ন করে)
                $profile_data['followers_count'] = (int)$profile_data['followers_count'];
                $profile_data['following_count'] = (int)$profile_data['following_count'];
                
                // 🔥 Boolean conversion for Flutter
                $profile_data['is_following'] = ($profile_data['is_following'] > 0); 
                $profile_data['is_following_viewer'] = ($profile_data['is_following_viewer'] > 0);
                
                // এটা কি আমার নিজের প্রোফাইল?
                $profile_data['is_own_profile'] = ($auth_user_id === $target_user_id);

                $response['profile'] = $profile_data;

                // --- ধাপ ২: ওই ইউজারের সব পোস্ট আনা ---
                $stmt_posts = $conn->prepare("
                    SELECT 
                        p.post_id, 
                        p.user_id,
                        p.post_content, 
                        p.image_url, 
                        p.created_at,
                        u.full_name, 
                        u.profile_picture_url,
                        
                        -- লাইক এবং কমেন্ট কাউন্ট
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
                        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
                        
                        -- আমি লাইক দিয়েছি কি না
                        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id AND user_id = ?) as is_liked

                    FROM posts p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.user_id = ?
                    ORDER BY p.created_at DESC
                ");
                
                $stmt_posts->bind_param("ii", $auth_user_id, $target_user_id);
                $stmt_posts->execute();
                $result_posts = $stmt_posts->get_result();
                
                $posts_list = array();
                while($row = $result_posts->fetch_assoc()) {
                    $row['like_count'] = (int)$row['like_count'];
                    $row['comment_count'] = (int)$row['comment_count'];
                    $row['is_liked'] = ($row['is_liked'] > 0); // Boolean Check
                    
                    // Flutter এর মডেলের সাথে নাম মিল রাখার জন্য
                    $row['userId'] = $row['user_id']; // Optional alias
                    
                    $posts_list[] = $row;
                }
                
                $response['posts'] = $posts_list;
                $stmt_posts->close();

            } else {
                http_response_code(404);
                $response['status'] = 'error';
                $response['message'] = 'User not found.';
            }
        } else {
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Database query failed.';
        }
        $stmt_user->close();

    } else {
        http_response_code(400);
        $response['status'] = 'error';
        $response['message'] = 'User ID is required.';
    }

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Please use GET.';
}

$conn->close();
echo json_encode($response);
?>