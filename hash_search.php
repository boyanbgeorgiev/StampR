<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

// Database connection
$dsn = 'mysql:host=localhost;dbname=tuesfest;charset=utf8mb4';
$username = 'admin';
$password = 'Bobiphpmyadmin1!';

try {
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Check if a hash was provided
if (!isset($_GET['hash'])) {
    echo json_encode(["status" => "error", "message" => "No hash provided"]);
    exit;
}

$fileHash = $_GET['hash'];

// Search for the hash in the database
$stmt = $pdo->prepare("SELECT file_hash, timestamp, serial_number FROM timestamps WHERE file_hash = ?");
$stmt->execute([$fileHash]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    echo json_encode([
        "status" => "found",
        "file_hash" => $record['file_hash'],
        "timestamp" => $record['timestamp'],
        "serial_number" => $record['serial_number']
    ]);
} else {
    echo json_encode(["status" => "not_found"]);
}
?>
