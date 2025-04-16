<?php
session_start();

$filename = $_GET['file'] ?? '';

$uploadsDir = __DIR__ . '/uploads/';
$filePath = realpath($uploadsDir . $filename);

// Check if file exists and is within the uploads directory
if (!$filePath || strpos($filePath, realpath($uploadsDir)) !== 0 || !file_exists($filePath)) {
    http_response_code(404);
    exit("Файлът не е достъпен.");
}

// Optional: verify user owns this file based on session/user_id/etc

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
