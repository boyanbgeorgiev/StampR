<?php
// Debug mode (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// === 1. DB CONNECTION ===
$dsn = 'mysql:host=localhost;dbname=tuesfest;charset=utf8mb4';
$username = 'admin';
$password = 'Bobiphpmyadmin1!';

try {
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed", "error" => $e->getMessage()]);
    exit;
}

// === 1. Check if user is logged in via cookie ===
$userId = null;
if (isset($_COOKIE['loggedin']) && $_COOKIE['loggedin'] === "1" && isset($_COOKIE['user_id'])) {
    $userId = (int) $_COOKIE['user_id']; // safe cast
}

// === 2. VALIDATE FILE ===
// Check file upload errors including size limits
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Няма избран файл."]);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    http_response_code(413); // Payload Too Large
    echo json_encode(["status" => "error", "message" => "Файлът е твърде голям. Моля, изберете по-малък файл."]);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Грешка при качване на файла (код: {$file['error']})"]);
    exit;
}

// === 3. CHECK FILE SIZE ===
// Set size limit to 20MB (20 * 1024 * 1024 bytes = 20MB)
$maxFileSize = 20 * 1024 * 1024; // 20MB in bytes
if ($file['size'] > $maxFileSize) {
    http_response_code(413); // Payload Too Large
    echo json_encode(["status" => "error", "message" => "Файлът надвишава лимита от 20 MB."]);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileHash = hash_file('sha256', $fileTmpPath);

// === 4. CHECK FOR EXISTING HASH ===
$stmt = $pdo->prepare("SELECT timestamp, serial_number FROM timestamps WHERE file_hash = ?");
$stmt->execute([$fileHash]);
if ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode([
        "status" => "exists",
        "message" => "Файлът вече е удостоверен.",
        "file_hash" => $fileHash,
        "timestamp" => $existing['timestamp'],
        "serial_number" => $existing['serial_number']
    ]);
    exit;
}

// === 5. GENERATE TIMESTAMP REQUEST ===
$requestFile = tempnam(sys_get_temp_dir(), 'tsq_');
$responseFile = tempnam(sys_get_temp_dir(), 'tsr_');

exec("openssl ts -query -data " . escapeshellarg($fileTmpPath) . " -cert -sha256 -no_nonce -out " . escapeshellarg($requestFile), $out1, $code1);
if ($code1 !== 0 || !file_exists($requestFile)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Грешка при създаване на заявка (TSQ)."]);
    exit;
}

// === 6. CONTACT TSA ===
$tsa_url = "http://username:password@tsatest.b-trust.org"; // <-- Replace with real creds
exec("curl -s -S -H 'Content-Type: application/timestamp-query' --data-binary @" . escapeshellarg($requestFile) . " " . escapeshellarg($tsa_url) . " -o " . escapeshellarg($responseFile), $out2, $code2);

if ($code2 !== 0 || !file_exists($responseFile)) {
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "TSA не отговори."]);
    exit;
}

// === 7. VALIDATE TSA RESPONSE ===
$tsaResponseText = shell_exec("openssl ts -reply -in " . escapeshellarg($responseFile) . " -text");
if (!$tsaResponseText) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "OpenSSL не успя да валидира отговора."]);
    exit;
}

// === 8. PARSE TIMESTAMP ===
preg_match('/Time stamp: (.*?)\n/', $tsaResponseText, $matchTime);
$timestampRaw = trim($matchTime[1] ?? '');
$timestamp = date("Y-m-d H:i:s", strtotime($timestampRaw));

// === 9. PARSE SERIAL NUMBER ===
preg_match('/Serial number: (.*?)\n/', $tsaResponseText, $matchSerial);
$serialNumber = trim($matchSerial[1] ?? '');

if (!$timestamp || !$serialNumber) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Неуспешно извличане на timestamp или сериен номер."]);
    exit;
}

// === 7. Save to DB (optionally with user_id) ===
if ($userId !== null) {
    $stmt = $pdo->prepare("INSERT INTO timestamps (user_id, file_hash, timestamp, serial_number) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $fileHash, $timestamp, $serialNumber]);
} else {
    $stmt = $pdo->prepare("INSERT INTO timestamps (file_hash, timestamp, serial_number) VALUES (?, ?, ?)");
    $stmt->execute([$fileHash, $timestamp, $serialNumber]);
}

// === 10. RETURN RESPONSE ===
echo json_encode([
    "status" => "success",
    "message" => "Удостоверяването е успешно.",
    "file_hash" => $fileHash,
    "timestamp" => $timestamp,
    "serial_number" => $serialNumber
]);

// Optional: clean up temp files
@unlink($requestFile);
@unlink($responseFile);
?>
