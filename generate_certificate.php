<?php
require_once('tcpdf/tcpdf.php');
require_once('phpqrcode/qrlib.php');

// Get POST data
$timestamp = $_POST['timestamp'] ?? null;
$hash = $_POST['hash'] ?? null;
$serial = $_POST['serial'] ?? null;

if (!$timestamp || !$hash || !$serial) {
  http_response_code(400);
  echo "Missing data";
  exit;
}

// Generate QR code image (temporary)
$verifyURL = "https://ethreal.eu/verify.php?serial=" . urlencode($serial);
$tmpQR = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
QRcode::png($verifyURL, $tmpQR, QR_ECLEVEL_H, 4);

// Create new PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Timamp Vault');
$pdf->SetTitle('Сертификат');
$pdf->SetHeaderData('', 0, 'Сертификат за времево удостоверение', '');
$pdf->setHeaderFont(Array('dejavusans', '', 12));
$pdf->setFooterFont(Array('dejavusans', '', 10));
$pdf->SetMargins(20, 30, 20);
$pdf->AddPage();

// Bulgarian font with UTF-8
$pdf->SetFont('dejavusans', '', 12);
$pdf->Write(10, "HASH: $hash\n");
$pdf->Write(10, "Време: $timestamp\n");
$pdf->Write(10, "Сериен номер: $serial\n");
$pdf->Ln(10);

// Add QR image
$pdf->Image($tmpQR, $pdf->GetX(), $pdf->GetY(), 40, 40);

// Clean temp file
register_shutdown_function(function () use ($tmpQR) {
  @unlink($tmpQR);
});

// Footer
$pdf->Ln(50);
$pdf->SetFont('dejavusans', '', 10);
$pdf->Write(10, "Провери сертификата на: $verifyURL");

$pdf->Output("certificate-$serial.pdf", 'I');
exit;
?>
