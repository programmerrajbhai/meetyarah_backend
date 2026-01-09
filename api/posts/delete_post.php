<?php
include 'db_connect.php';
include 'auth_middleware.php'; // ১. সিকিউরিটি গার্ড যুক্ত করি

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


// ২. টোকেন চেক করে ইউজারের আইডি নিই
$authenticated_user_id = get_authenticated_user_id($conn); 

$response = array();

// আমরা এই কাজটিও POST রিকোয়েস্ট দিয়ে করবো
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // ৩. চেক করি যে 'post_id' পাঠানো হয়েছে কি না
    if (isset($input['post_id'])) {
        $post_id = $input['post_id'];

        // ৪. পোস্ট ডিলিট করি
        // এই SQL কোয়েরিটি খুবই নিরাপদ:
        // এটি পোস্টটি ডিলিট করবে *শুধুমাত্র যদি* 'post_id' মেলে 
        // *এবং* 'user_id' টোকেন থেকে পাওয়া আইডির সাথে মেলে।
        $stmt_delete = $conn->prepare("DELETE FROM posts WHERE post_id = ? AND user_id = ?");
        $stmt_delete->bind_param("ii", $post_id, $authenticated_user_id);
        
        if ($stmt_delete->execute()) {
            
            // ৫. চেক করি যে আসলেই কোনো রো ডিলিট হয়েছে কি না
            if ($stmt_delete->affected_rows > 0) {
                // সফলভাবে ডিলিট হয়েছে
                $response['status'] = 'success';
                $response['message'] = 'Post deleted successfully.';
            } else {
                // কোনো রো ডিলিট হয়নি। এর মানে:
                // ক) পোস্টটি খুঁজে পাওয়া যায়নি, অথবা
                // খ) পোস্টটি এই ইউজারের নয়।
                http_response_code(403); // Forbidden
                $response['status'] = 'error';
                $response['message'] = 'Delete failed: Post not found or you do not have permission.';
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Query failed: ' . $stmt_delete->error;
        }
        $stmt_delete->close();

    } else {
        $response['status'] = 'error';
        $response['message'] = 'Required field (post_id) is missing.';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>