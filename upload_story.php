<?php
require_once 'db_connect.php';
require_once 'auth_middleware.php';

header("Content-Type: application/json; charset=UTF-8");

// ✅ CORS (Flutter Web friendly)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// ✅ Preflight handle
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'OK']);
    exit();
}

// ✅ Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit();
}

// ✅ Auth must
$auth_user_id = get_authenticated_user_id($conn);

/** Upload error helper */
function file_upload_error_message($code) {
    $map = [
        UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
        UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE (php.ini upload_max_filesize too small)',
        UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
        UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
        UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
        UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
        UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE (permission)',
        UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
    ];
    return $map[$code] ?? ("Unknown upload error: " . $code);
}

// ✅ Must have file key = media
if (!isset($_FILES['media'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'media file is required',
        'debug' => [
            'files_keys' => array_keys($_FILES),
            'note' => 'Body must be multipart/form-data. Key=media, Type=File. Do NOT set Content-Type manually.'
        ]
    ]);
    exit();
}

// ✅ Upload errors
if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Upload failed',
        'debug' => [
            'error_code' => $_FILES['media']['error'],
            'error_text' => file_upload_error_message($_FILES['media']['error']),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ]
    ]);
    exit();
}

// ✅ Upload dir
$uploadDir = __DIR__ . '/uploads/';

// Create folder if missing
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create uploads directory',
            'debug' => ['uploadDir' => $uploadDir]
        ]);
        exit();
    }
}

// Ensure writable
if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Uploads directory is not writable',
        'debug' => [
            'uploadDir' => $uploadDir,
            'hint_mac_xampp' => 'sudo chmod -R 777 /Applications/XAMPP/xamppfiles/htdocs/api/uploads',
            'hint_linux' => 'chmod -R 775 api/uploads && chown -R www-data:www-data api/uploads'
        ]
    ]);
    exit();
}

$tmp = $_FILES['media']['tmp_name'];
if (!is_uploaded_file($tmp)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Temp file is not a valid uploaded file',
        'debug' => ['tmp_name' => $tmp]
    ]);
    exit();
}

// ✅ Validate extension
$originalName = $_FILES['media']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$imageExts = ['jpg','jpeg','png','webp','gif'];
$videoExts = ['mp4','mov','mkv','avi','webm'];

$media_type = null;
if (in_array($ext, $imageExts, true)) $media_type = 'image';
if (in_array($ext, $videoExts, true)) $media_type = 'video';

if ($media_type === null) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file type',
        'debug' => ['ext' => $ext]
    ]);
    exit();
}

// ✅ Save file
$fileName = uniqid('story_', true) . '_' . $auth_user_id . '.' . $ext;
$dest = $uploadDir . $fileName;

if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save media',
        'debug' => [
            'tmp_name' => $tmp,
            'dest' => $dest,
            'uploadDir_writable' => is_writable($uploadDir),
            'php_upload_tmp_dir' => ini_get('upload_tmp_dir'),
        ]
    ]);
    exit();
}

// ✅ DB insert (expire after 24h)
$stmt = $conn->prepare("
    INSERT INTO stories (user_id, media_url, media_type, expires_at)
    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
");
$stmt->bind_param("iss", $auth_user_id, $fileName, $media_type);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'DB error',
        'debug' => $stmt->error
    ]);
    $stmt->close();
    $conn->close();
    exit();
}

// ✅ Full URL generate
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$full = "$protocol://$host$script_dir/uploads/$fileName";

echo json_encode([
    'status' => 'success',
    'message' => 'Story uploaded',
    'story_id' => $stmt->insert_id,
    'file_name' => $fileName,
    'media_url' => $full,
    'media_type' => $media_type
]);

$stmt->close();
$conn->close();
?>
