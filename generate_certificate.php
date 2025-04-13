<?php
require_once('tcpdf/tcpdf.php');
require_once('phpqrcode/qrlib.php');

// Получаване на данните от POST (ако някое поле липсва, ще се използва 'N/A')
$timestamp = $_POST['timestamp'] ?? null;
$hash = $_POST['hash'] ?? null;
$serial = $_POST['serial'] ?? null;
$first_name = $_POST['first_name'] ?? 'N/A';
$last_name = $_POST['last_name'] ?? 'N/A';
$email = $_POST['email'] ?? 'N/A';
$phone = $_POST['phone'] ?? 'N/A';
$file_name = $_POST['file_name'] ?? 'N/A';

if (!$timestamp || !$hash || !$serial) {
  http_response_code(400);
  echo "Missing required data";
  exit;
}

// Генериране на QR код (временен файл)
$verifyURL = "https://stampr.eu/verify.php?serial=" . urlencode($serial);
$tmpQR = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
QRcode::png($verifyURL, $tmpQR, QR_ECLEVEL_H, 4);

// Създаване на нов PDF документ
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Настройване на информацията за документа
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('StampR');
$pdf->SetTitle('Сертификат за времево удостоверение');
$pdf->SetSubject('Сертификат');

// Премахване на подразбиращите се хедър и футър
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Настройване на маргините и автоматичното прекъсване на страницата
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(TRUE, 20);

// Добавяне на страница
$pdf->AddPage();

// Добавяне на плейсхолдър за лого (ако има файл "logo.png", то се показва, иначе се добавя текстов плейсхолдър)
$logoFile = 'logo.png';
if (file_exists($logoFile)) {
    $pdf->Image($logoFile, 20, 10, 30, 30);
} else {
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetXY(20, 10);
    $pdf->Cell(30, 30, 'LOGO', 1, 0, 'C', 0, '', 0);
}

// Добавяне на заглавие на сертификата
$pdf->SetFont('dejavusans', 'B', 20);
$pdf->Ln(35);
$pdf->Cell(0, 10, 'СЕРТИФИКАТ ЗА ВРЕМЕВО УДОСТОВЕРЕНИЕ', 0, 1, 'C');

// Рисуване на линия под заглавието
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY(), $pdf->getPageWidth() - 20, $pdf->GetY());
$pdf->Ln(5);

// Текст за удостоверението
$pdf->SetFont('dejavusans', '', 12);
$certificateText = "Този сертификат удостоверява, че лицето, подписало документа, е успешно верифицирано чрез платформата StampR и признато за упълномощено. С удостоверението се гарантира автентичността и непроменимостта на подписания файл, като се осигурява доказателство за валидността на самоличността на потребителя. Документът представлява официално доказателство за доверие и легитимност в подписирането на цифрово съдържание.";
$pdf->MultiCell(0, 0, $certificateText, 0, 'J', false, 1, '', '', true);
$pdf->Ln(5);

// Информация за потребителя и файла
$details = "Име: $first_name\nФамилия: $last_name\nИмейл: $email\nТелефон: $phone\nИме на файла: $file_name\n\nHASH: $hash\nСериен номер: $serial\nВреме: $timestamp";
$pdf->MultiCell(0, 0, $details, 0, 'L', false, 1, '', '', true);
$pdf->Ln(5);

// Добавяне на QR код
$currentX = $pdf->GetX();
$currentY = $pdf->GetY();
$pdf->Image($tmpQR, $currentX, $currentY, 40, 40);

// Добавяне на линк за проверка под QR кода
$pdf->SetXY($currentX, $currentY + 42);
$pdf->SetFont('dejavusans', 'I', 10);
$pdf->Write(0, "Провери сертификата тук:\n$verifyURL");

// Изтриване на временния QR файл при приключване
register_shutdown_function(function () use ($tmpQR) {
    @unlink($tmpQR);
});

// Генериране на PDF и отпечатване в браузъра
$pdf->Output("certificate-$serial.pdf", 'I');
exit;
?>
