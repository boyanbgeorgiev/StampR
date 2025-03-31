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

// Check if a serial number was provided
if (!isset($_GET['serial'])) {
    echo json_encode(["status" => "error", "message" => "No serial number provided"]);
    exit;
}

$serialNumber = $_GET['serial'];

// Search for the serial number in the database
$stmt = $pdo->prepare("SELECT serial_number, file_hash, timestamp FROM timestamps WHERE serial_number = ?");
$stmt->execute([$serialNumber]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    echo json_encode([
        "status" => "found",
        "serial_number" => $record['serial_number'],
        "file_hash" => $record['file_hash'],
        "timestamp" => $record['timestamp']
    ]);
} else {
    echo json_encode(["status" => "not_found"]);
}
?>
