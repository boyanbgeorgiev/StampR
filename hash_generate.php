<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

// Check if a file was uploaded
if (!isset($_FILES['file'])) {
    echo json_encode(["status" => "error", "message" => "No file uploaded"]);
    exit;
}

// Check for file upload errors
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => "error", "message" => "File upload error: " . $_FILES['file']['error']]);
    exit;
}

// Generate the SHA-256 hash using PHP's built-in hash_file function
$fileTmpPath = $_FILES['file']['tmp_name'];
$fileHash = hash_file('sha256', $fileTmpPath);

// Return the hash to the client
echo json_encode(["status" => "success", "file_hash" => $fileHash]);
?>
