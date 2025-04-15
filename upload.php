<?php
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

// === 2. Check if user is logged in via cookie ===
$userId = null;
if (isset($_COOKIE['loggedin']) && $_COOKIE['loggedin'] === "1" && isset($_COOKIE['user_id'])) {
    $userId = (int) $_COOKIE['user_id'];
}

// === 3. VALIDATE FILE ===
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Няма избран файл."]);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    http_response_code(413);
    echo json_encode(["status" => "error", "message" => "Файлът е твърде голям."]);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Грешка при качване на файла (код: {$file['error']})"]);
    exit;
}

// === 4. VALIDATE SIZE ===
$maxFileSize = isset($_POST['public']) && $_POST['public'] == "1" ? 10 * 1024 * 1024 : 20 * 1024 * 1024;
if ($file['size'] > $maxFileSize) {
    http_response_code(413);
    echo json_encode(["status" => "error", "message" => "Файлът надвишава лимита от " . ($maxFileSize / (1024 * 1024)) . " MB."]);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = basename($file['name']);
$fileHash = hash_file('sha256', $fileTmpPath);

// === 5. CHECK IF FILE ALREADY EXISTS ===
$stmt = $pdo->prepare("SELECT timestamp, serial_number, file_name, uploader_name, uploader_email, uploader_phone, is_anonymous FROM timestamps WHERE file_hash = ?");
$stmt->execute([$fileHash]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo json_encode([
        "status" => "exists",
        "message" => "Файлът вече е удостоверен.",
        "timestamp" => $existing['timestamp'],
        "serial_number" => $existing['serial_number'],
        "file_name" => $existing['file_name'],
        "file_hash" => $fileHash,
        "uploader_name" => $existing['uploader_name'],
        "uploader_email" => $existing['uploader_email'],
        "uploader_phone" => $existing['uploader_phone'],
        "is_anonymous" => $existing['is_anonymous']
    ]);
    exit;
}

// === 6. MOVE TO /uploads IF PUBLIC ===
if (isset($_POST['public']) && $_POST['public'] == "1") {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($fileTmpPath, $targetPath)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Неуспешно качване на файла."]);
        exit;
    }

    $filePathForTSA = $targetPath;
} else {
    $filePathForTSA = $fileTmpPath;
}

// === 7. CREATE TSA REQUEST ===
$requestFile = tempnam(sys_get_temp_dir(), 'tsq_');
$responseFile = tempnam(sys_get_temp_dir(), 'tsr_');

exec("openssl ts -query -data " . escapeshellarg($filePathForTSA) . " -cert -sha256 -no_nonce -out " . escapeshellarg($requestFile), $out1, $code1);
if ($code1 !== 0 || !file_exists($requestFile)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Грешка при създаване на заявка (TSQ)."]);
    exit;
}

// === 8. SEND TO TSA ===
// Retrieve the TSA URL from a cookie or use the default
$tsa_url = $_COOKIE['tsa_url'] ?? "http://freetsa.org/tsr";

exec("curl -s -S -H 'Content-Type: application/timestamp-query' --data-binary @" . escapeshellarg($requestFile) . " " . escapeshellarg($tsa_url) . " -o " . escapeshellarg($responseFile), $out2, $code2);

if ($code2 !== 0 || !file_exists($responseFile)) {
    http_response_code(502);
    echo json_encode(["status" => "error", "message" => "TSA не отговори."]);
    exit;
}

$tsaResponseText = shell_exec("openssl ts -reply -in " . escapeshellarg($responseFile) . " -text");
if (!$tsaResponseText) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "OpenSSL не успя да валидира отговора."]);
    exit;
}

preg_match('/Time stamp: (.*?)\n/', $tsaResponseText, $matchTime);
$timestampRaw = trim($matchTime[1] ?? '');
$timestamp = date("Y-m-d H:i:s", strtotime($timestampRaw));

preg_match('/Serial number: (.*?)\n/', $tsaResponseText, $matchSerial);
$serialNumber = trim($matchSerial[1] ?? '');

if (!$timestamp || !$serialNumber) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Неуспешно извличане на timestamp или сериен номер."]);
    exit;
}

// === 9. EXTRACT USER INFO IF NOT ANONYMOUS ===
$isAnonymous = isset($_POST['anonymous']) && $_POST['anonymous'] == "1";
$uploaderName = null;
$uploaderEmail = null;
$uploaderPhone = null;

if (!$isAnonymous) {
    $uploaderName = $_COOKIE['first_name'] ?? null;
    if (isset($_COOKIE['last_name'])) {
        $uploaderName .= ' ' . $_COOKIE['last_name'];
    }
    $uploaderEmail = $_COOKIE['email'] ?? null;
    $uploaderPhone = $_COOKIE['phone'] ?? null;
}

// === 10. SAVE TO DB ===
$stmt = $pdo->prepare("INSERT INTO timestamps (user_id, file_hash, timestamp, serial_number, file_name, uploader_name, uploader_email, uploader_phone, is_anonymous)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $userId,
    $fileHash,
    $timestamp,
    $serialNumber,
    $fileName,
    $uploaderName,
    $uploaderEmail,
    $uploaderPhone,
    $isAnonymous ? 1 : 0
]);

// === 11. SUCCESS RESPONSE ===
echo json_encode([
    "status" => "success",
    "message" => "Удостоверяването е успешно.",
    "file_hash" => $fileHash,
    "timestamp" => $timestamp,
    "serial_number" => $serialNumber,
    "file_name" => $fileName
]);

@unlink($requestFile);
@unlink($responseFile);
?>
