<?php
require_once('tcpdf/tcpdf.php');
require_once('phpqrcode/qrlib.php');
require_once('db.php');

// Get POST data
$timestamp = $_POST['timestamp'] ?? null;
$hash = $_POST['hash'] ?? null;
$serial = $_POST['serial'] ?? null;
$file_name = $_POST['file_name'] ?? 'N/A';

if (!$timestamp || !$hash || !$serial) {
    http_response_code(400);
    echo "Missing required data";
    exit;
}

// Get user info
$stmt = $conn->prepare("SELECT is_anonymous, uploader_name, uploader_email, uploader_phone, tsa_type FROM timestamps WHERE file_hash = ?");
$stmt->bind_param("s", $hash);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$is_anonymous = $row['is_anonymous'];
$nameParts = explode(" ", $row['uploader_name'] ?? 'Анонимен');
$firstName = $nameParts[0] ?? 'N/A';
$lastName = $nameParts[1] ?? '';
$email = $row['uploader_email'] ?? 'N/A';
$phone = $row['uploader_phone'] ?? 'N/A';
$tsa_type = $row['tsa_type'] ?? 'Неизвестен';

// QR code
$verifyURL = "https://stampr.eu/verify.php?serial=" . urlencode($serial);
$tmpQR = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
QRcode::png($verifyURL, $tmpQR, QR_ECLEVEL_H, 4);

// PDF init
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(25, 25, 25);
$pdf->AddPage();

// === Title ===
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'СЕРТИФИКАТ ЗА ВРЕМЕВО УДОСТОВЕРЕНИЕ', 0, 1, 'L');

// === Intro ===
$pdf->SetFont('dejavusans', '', 11);
$intro = "Този сертификат удостоверява, че лицето, подписало документа, е успешно верифицирано чрез платформата StampR и признато за упълномощено.
С удостоверението се гарантира автентичността и непроменимостта на подписания файл, като се осигурява доказателство за валидността на самоличността на потребителя. Документът представлява официално доказателство за доверие и легитимност в подписаните на цифрово съдържание.";
$pdf->MultiCell(0, 0, $intro, 0, 'L');
$pdf->Ln(6);

// === Info Fields ===
$pdf->SetFont('dejavusans', 'B', 11);
if ($is_anonymous) {
    $pdf->Write(0, "Приносител: Анонимен");
} else {
    $pdf->Write(0, "Име: ");
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Write(0, $firstName);
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Write(0, "Фамилия: ");
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Write(0, $lastName);
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Write(0, "Имейл: ");
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Write(0, $email);
    $pdf->Ln(6);

    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Write(0, "Телефон: ");
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Write(0, $phone);
    $pdf->Ln(6);
}

// File name
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Write(0, "Име на файла: ");
$pdf->SetFont('dejavusans', '', 11);
$pdf->Write(0, $file_name);
$pdf->Ln(6);

// Hash
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Write(0, "HASH: ");
$pdf->SetFont('dejavusans', '', 11);
$pdf->Write(0, $hash);
$pdf->Ln(6);

// Serial number
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Write(0, "Сериен номер: ");
$pdf->SetFont('dejavusans', '', 11);
$pdf->Write(0, $serial);
$pdf->Ln(6);

// Timestamp
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Write(0, "Време: ");
$pdf->SetFont('dejavusans', '', 11);
$pdf->Write(0, $timestamp);
$pdf->Ln(10);

// === QR code + link ===
$pdf->Image($tmpQR, $pdf->GetX(), $pdf->GetY(), 50, 50);
$pdf->Ln(55);

$pdf->SetFont('dejavusans', 'I', 10);
$pdf->Write(0, "Сертификатът може да бъде проверен и на страницата на StampR:");
$pdf->Ln(5);
$pdf->SetTextColor(0, 0, 255);
$pdf->Write(0, $verifyURL, $verifyURL);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(15);

// === StampR Logo bottom right ===
$logoFile = 'logo.png';
if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 155, 250, 35); // Adjust as needed
}

// Cleanup
register_shutdown_function(function () use ($tmpQR) {
    @unlink($tmpQR);
});

$pdf->Output("certificate-$serial.pdf", 'I');
exit;
?>
