<?php
include 'db_connect.php'; // ডাটাবেস কানেকশন

$response = array();

// এটি GET রিকোয়েস্ট হবে এবং URL-এ user_id আশা করবে
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    if (isset($_GET['user_id'])) {
        $user_id = $_GET['user_id'];

        // --- প্রথম ধাপ: ইউজারের প্রোফাইল তথ্য আনা ---
        $stmt_user = $conn->prepare("SELECT user_id, username, full_name, profile_picture_url, created_at FROM users WHERE user_id = ?");
        $stmt_user->bind_param("i", $user_id);
        
        if ($stmt_user->execute()) {
            $result_user = $stmt_user->get_result();
            if ($result_user->num_rows == 1) {
                
                $response['status'] = 'success';
                $response['profile'] = $result_user->fetch_assoc(); // প্রোফাইল তথ্য রেসপন্সে যোগ করি

                // --- দ্বিতীয় ধাপ: ওই ইউজারের সব পোস্ট আনা ---
                // (get_all_posts.php-এর মতো, কিন্তু শুধু এই user_id-এর জন্য)
                $stmt_posts = $conn->prepare("
                    SELECT 
                        p.post_id, 
                        p.post_content, 
                        p.image_url, 
                        p.created_at,
                        COUNT(DISTINCT l.like_id) AS like_count,
                        COUNT(DISTINCT c.comment_id) AS comment_count
                    FROM 
                        posts p
                    LEFT JOIN 
                        likes l ON p.post_id = l.post_id
                    LEFT JOIN 
                        comments c ON p.post_id = c.post_id
                    WHERE 
                        p.user_id = ?  -- <-- শুধু এই ইউজারের পোস্ট
                    GROUP BY 
                        p.post_id
                    ORDER BY 
                        p.created_at DESC
                ");
                
                $stmt_posts->bind_param("i", $user_id);
                $stmt_posts->execute();
                $result_posts = $stmt_posts->get_result();
                
                $posts_list = array();
                while($row = $result_posts->fetch_assoc()) {
                    $row['like_count'] = (int)$row['like_count'];
                    $row['comment_count'] = (int)$row['comment_count'];
                    $posts_list[] = $row;
                }
                
                $response['posts'] = $posts_list; // পোস্টের লিস্ট রেসপন্সে যোগ করি
                $stmt_posts->close();

            } else {
                $response['status'] = 'error';
                $response['message'] = 'User not found.';
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to fetch user profile.';
        }
        $stmt_user->close();

    } else {
        $response['status'] = 'error';
        $response['message'] = 'User ID is required.';
    }

} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Please use GET.';
}

$conn->close();
echo json_encode($response);
?>