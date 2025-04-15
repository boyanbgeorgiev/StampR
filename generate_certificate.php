<?php
require_once('tcpdf/tcpdf.php');
require_once('phpqrcode/qrlib.php');
require_once('db.php'); // Include your database connection

// Получаване на данните от POST
$timestamp   = $_POST['timestamp']   ?? null;
$hash        = $_POST['hash']        ?? null;
$serial      = $_POST['serial']      ?? null;
$file_name   = $_POST['file_name']   ?? 'N/A';

if (!$timestamp || !$hash || !$serial) {
    http_response_code(400);
    echo "Missing required data";
    exit;
}

// Query the database to get the `is_anonymous` value
$stmt = $conn->prepare("SELECT is_anonymous, uploader_name, uploader_email, uploader_phone FROM timestamps WHERE file_hash = ?");
$stmt->bind_param("s", $hash);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo "File not found in the database.";
    exit;
}

$is_anonymous = $row['is_anonymous'];
$uploader_name = $row['uploader_name'] ?? 'N/A';
$uploader_email = $row['uploader_email'] ?? 'N/A';
$uploader_phone = $row['uploader_phone'] ?? 'N/A';

// Генериране на QR код (временен файл)
$verifyURL = "https://stampr.eu/verify.php?serial=" . urlencode($serial);
$tmpQR = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
QRcode::png($verifyURL, $tmpQR, QR_ECLEVEL_H, 4);

// Създаване на нов PDF документ
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Настройки на документа
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('StampR');
$pdf->SetTitle('Сертификат за времево удостоверение');
$pdf->SetSubject('Сертификат');

// Без хедър и футър
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Маргини и автоматичен page break
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);

// Добавяне на страница
$pdf->AddPage();

// Лого (ако съществува)
$logoFile = 'logo.png';
if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 20, 10, 30, 30);
} else {
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetXY(20, 10);
    $pdf->Cell(30, 30, 'LOGO', 1, 0, 'C', 0, '', 0);
}

// Заглавие
$pdf->SetFont('dejavusans', 'B', 20);
$pdf->Ln(35);
$pdf->Cell(0, 10, 'СЕРТИФИКАТ ЗА ВРЕМЕВО УДОСТОВЕРЕНИЕ', 0, 1, 'C');

// Линия под заглавието
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY(), $pdf->getPageWidth() - 20, $pdf->GetY());
$pdf->Ln(5);

// Описание
$pdf->SetFont('dejavusans', '', 12);
$certificateText = "Този сертификат удостоверява, че лицето, подписало документа, е успешно верифицирано чрез платформата StampR и признато за упълномощено. С удостоверението се гарантира автентичността и непроменимостта на подписания файл, като се осигурява доказателство за валидността на самоличността на потребителя.";
$pdf->MultiCell(0, 0, $certificateText, 0, 'J', false, 1, '', '', true);
$pdf->Ln(5);

// Детайли
if ($is_anonymous) {
    $userDetails = "Приносител: Анонимен";
} else {
    $userDetails = "Име: $uploader_name\nИмейл: $uploader_email\nТелефон: $uploader_phone";
}

$details = "$userDetails\nИме на файла: $file_name\n\nHASH: $hash\nСериен номер: $serial\nВреме: $timestamp";
$pdf->MultiCell(0, 0, $details, 0, 'L', false, 1, '', '', true);
$pdf->Ln(5);

// QR код
$currentX = $pdf->GetX();
$currentY = $pdf->GetY();
$pdf->Image($tmpQR, $currentX, $currentY, 40, 40);

// Линк за проверка
$pdf->SetXY($currentX, $currentY + 42);
$pdf->SetFont('dejavusans', 'I', 10);
$pdf->Write(0, "Провери сертификата тук:\n$verifyURL");

// Изтриване на временния QR файл
register_shutdown_function(function () use ($tmpQR) {
    @unlink($tmpQR);
});

// Изход
$pdf->Output("certificate-$serial.pdf", 'I');
exit;
?>
