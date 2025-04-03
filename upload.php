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
    $userId = (int) $_COOKIE['user_id']; // safe cast
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

// === 4. VALIDATE SIZE
$maxFileSize = isset($_POST['public']) && $_POST['public'] == "1" ? 10 * 1024 * 1024 : 20 * 1024 * 1024;
if ($file['size'] > $maxFileSize) {
    http_response_code(413);
    echo json_encode(["status" => "error", "message" => "Файлът надвишава лимита от " . ($maxFileSize / (1024 * 1024)) . " MB."]);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = basename($file['name']);

// === 5. HASH BEFORE MOVING (because tmp_name is valid only now)
$fileHash = hash_file('sha256', $fileTmpPath);

// Check if already exists
$stmt = $pdo->prepare("SELECT timestamp, serial_number, file_name FROM timestamps WHERE file_hash = ?");
$stmt->execute([$fileHash]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo json_encode([
        "status" => "exists",
        "message" => "Файлът вече е удостоверен.",
        "timestamp" => $existing['timestamp'],
        "serial_number" => $existing['serial_number'],
        "file_name" => $existing['file_name'],
    ]);
    exit;
}

// === 6. If public: move to uploads and use that path
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

// === 7. TSA: Create request
$requestFile = tempnam(sys_get_temp_dir(), 'tsq_');
$responseFile = tempnam(sys_get_temp_dir(), 'tsr_');

exec("openssl ts -query -data " . escapeshellarg($filePathForTSA) . " -cert -sha256 -no_nonce -out " . escapeshellarg($requestFile), $out1, $code1);
if ($code1 !== 0 || !file_exists($requestFile)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Грешка при създаване на заявка (TSQ)."]);
    exit;
}

// === 8. Send to TSA
$tsa_url = "http://username:password@tsatest.b-trust.org";
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

// === 9. SAVE TO DB
if ($userId !== null) {
    $stmt = $pdo->prepare("INSERT INTO timestamps (user_id, file_hash, timestamp, serial_number, file_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $fileHash, $timestamp, $serialNumber, $fileName]);
} else {
    $stmt = $pdo->prepare("INSERT INTO timestamps (file_hash, timestamp, serial_number, file_name) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fileHash, $timestamp, $serialNumber, $fileName]);
}

// === 10. RESPONSE
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
