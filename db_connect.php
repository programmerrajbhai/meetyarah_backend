<?php
// ১. CORS এবং ফরম্যাট হেডার (সব ফাইলের জন্য কমন)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ২. প্রি-ফ্লাইট (OPTIONS) রিকোয়েস্ট হ্যান্ডেলিং
// ব্রাউজার যখন ডাটা পাঠানোর আগে চেক করে, তখন এই ব্লকটি কাজ করে।
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ৩. ডাটাবেস কানেকশন
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "social_app";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
?>