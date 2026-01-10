<?php
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$response = array();
// ছবি সেভ করার ফোল্ডারের নাম
$upload_dir = "uploads/";


$uploadFileDir = './uploads/';
if (!is_dir($uploadFileDir)) {
    // ০৭৭৭ পারমিশন দিয়ে ফোল্ডার তৈরি করবে
    mkdir($uploadFileDir, 0777, true); 
} else {
    // ফোল্ডার থাকলে পারমিশন আপডেট করবে
    chmod($uploadFileDir, 0777); 
}


// ফোল্ডার না থাকলে তৈরি করে নেবে
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_FILES['image'])) {
        
        $file_name = basename($_FILES["image"]["name"]);
        // ফাইলের নামের সাথে ইউনিক আইডি যোগ করা হচ্ছে যাতে নাম ডুপ্লিকেট না হয়
        $target_file_name = uniqid() . "_" . $file_name;
        $target_file_path = $upload_dir . $target_file_name;

        // ফাইলটি টেম্পোরারি ফোল্ডার থেকে আমাদের uploads ফোল্ডারে মুভ করা
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file_path)) {
            
            // পূর্ণাঙ্গ ইমেজ URL তৈরি করা
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $script_dir = dirname($_SERVER['PHP_SELF']);
            
            // লক্ষ্য করুন: এখানে স্ল্যাশ (/) ঠিকভাবে হ্যান্ডেল করা হয়েছে
            $final_image_url = "$protocol://$host$script_dir/$target_file_path";

            $response['status'] = 'success';
            $response['message'] = 'Image uploaded successfully.';
            $response['image_url'] = $final_image_url;
            
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Failed to move uploaded file. Check folder permissions.';
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'No image file found in request.';
    }

} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>