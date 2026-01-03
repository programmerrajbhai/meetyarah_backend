<?php
header("Content-Type: application/json"); // রেসপন্স JSON ফরম্যাটে হবে
header("Access-Control-Allow-Origin: *"); // সব ডোমেইন থেকে অ্যাক্সেস অ্যালাউ করা হলো

// XAMPP ডাটাবেস সেটিংস (লোকালহোস্ট)
$servername = "localhost";
$username = "root";      // XAMPP এর ডিফল্ট ইউজারনেম
$password = "";          // XAMPP এ ডিফল্ট পাসওয়ার্ড ফাঁকা থাকে
$dbname = "social_app"; // এখানে আপনার লোকাল ডাটাবেসের নাম দিন (উদাহরণ: my_database)

// ডাটাবেস কানেকশন তৈরি
$conn = new mysqli($servername, $username, $password, $dbname);

// কানেকশন চেক
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// বাংলা বা অন্য ভাষার সাপোর্টের জন্য এনকোডিং সেট করা
$conn->set_charset("utf8");

?>