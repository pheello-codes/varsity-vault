<?php
date_default_timezone_set('Africa/Johannesburg');
$page_title = 'Download Note';
include 'includes/config.php';
include 'includes/auth_check.php';

// Load FPDI and TCPDF for watermarking
require_once __DIR__ . '/vendor/autoload.php';
if (!class_exists('FPDI') && file_exists(__DIR__ . '/vendor/setasign/fpdi/src/FPDI.php')) {
    require_once __DIR__ . '/vendor/setasign/fpdi/src/FPDI.php';
}
if (!class_exists('TCPDF') && file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
}

/**
 * Add watermark to PDF and stream to browser without saving
 * Returns the watermarked PDF content as a string, or empty string on failure
 */
function addPdfWatermark($pdfPath, $buyerEmail, $buyerName = '') {
    try {
        // Check if FPDI is available
        if (!class_exists('FPDI')) {
            error_log("FPDI class not found. Watermarking unavailable.");
            return '';
        }

        // Create FPDI instance
        $pdf = new FPDI('P', 'mm', 'A4');
        $pdf->setSourceFile($pdfPath);
        $pageCount = $pdf->getNumPages();

        // Add watermark to each page
        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            $templateId = $pdf->importPage($pageNum);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            // Add watermark text at bottom center
            $watermarkText = "Purchased by $buyerEmail on " . date('Y-m-d') . " for personal use only. | Varsity Vault";
            
            // Set font: gray, italic, 10pt
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->SetTextColor(128, 128, 128); // Gray color

            // Set opacity to 30% (0.3)
            $pdf->SetAlpha(0.3);

            // Get page dimensions for centering
            $pageWidth = $pdf->GetPageWidth();
            $pageHeight = $pdf->GetPageHeight();
            
            // Position text at bottom center
            $bottomMargin = 15; // 15mm from bottom
            $pdf->SetXY(10, $pageHeight - $bottomMargin);
            $pdf->Cell($pageWidth - 20, 10, $watermarkText, 0, 1, 'C');

            // Reset opacity
            $pdf->SetAlpha(1);
        }

        // Return PDF content as string
        return $pdf->Output('', 'S'); // 'S' returns as string
    } catch (Exception $e) {
        error_log("PDF watermarking error: " . $e->getMessage());
        return '';
    }
}

if (!isset($_GET['note_id']) || !is_numeric($_GET['note_id'])) {
    header('Location: dashboard.php');
    exit();
}

$note_id = (int)$_GET['note_id'];
$user_id = $_SESSION['user_id'];

$note_stmt = $conn->prepare("SELECT n.id, n.title, n.file_path, n.seller_id, n.status, u.name AS seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.id = ?");
$note_stmt->bind_param('i', $note_id);
$note_stmt->execute();
$note = $note_stmt->get_result()->fetch_assoc();

if (!$note || empty($note['file_path'])) {
    header('HTTP/1.1 404 Not Found');
    echo 'Note not available for download.';
    exit();
}

$auth_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$auth_stmt->bind_param('i', $user_id);
$auth_stmt->execute();
$user_data = $auth_stmt->get_result()->fetch_assoc();
$is_admin = !empty($user_data['is_admin']);

$authorized = false;

$purchase_stmt = $conn->prepare("SELECT p.id FROM purchases p JOIN notes n ON p.note_id = n.id WHERE p.user_id = ? AND p.note_id = ? AND n.status = 'approved' LIMIT 1");
$purchase_stmt->bind_param('ii', $user_id, $note_id);
$purchase_stmt->execute();
if ($purchase_stmt->get_result()->num_rows > 0) {
    $authorized = true;
}

if ($note['seller_id'] === $user_id || $is_admin) {
    $authorized = true;
}

if (!$authorized) {
    header('HTTP/1.1 403 Forbidden');
    echo 'You are not authorized to download this file.';
    exit();
}

$full_path = realpath(__DIR__ . '/' . $note['file_path']);
$uploads_dir = realpath(__DIR__ . '/uploads/notes');

if (!$full_path || strpos($full_path, $uploads_dir) !== 0 || !is_file($full_path)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File could not be found.';
    exit();
}

$log_stmt = $conn->prepare("INSERT INTO download_logs (user_id, note_id, ip_address) VALUES (?, ?, ?)");
$log_stmt->bind_param('iis', $user_id, $note_id, $_SERVER['REMOTE_ADDR']);
$log_stmt->execute();

// Determine if user is a buyer (not seller/admin)
$is_buyer = !($note['seller_id'] === $user_id || $is_admin);

// If buyer, fetch email and apply watermark
$pdfContent = null;
if ($is_buyer) {
    $email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $email_stmt->bind_param('i', $user_id);
    $email_stmt->execute();
    $user_email_data = $email_stmt->get_result()->fetch_assoc();
    $buyer_email = $user_email_data['email'] ?? 'unknown@varsity-vault.local';

    // Attempt to add watermark
    $pdfContent = addPdfWatermark($full_path, $buyer_email);

    // If watermarking fails, log it and fall back to original
    if (empty($pdfContent)) {
        error_log("Watermarking failed for note_id: $note_id, user_id: $user_id. Streaming original PDF.");
        $pdfContent = null;
    }
}

// Stream PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!empty($pdfContent)) {
    // Stream watermarked PDF
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
} else {
    // Stream original PDF (fallback or non-buyer)
    header('Content-Length: ' . filesize($full_path));
    header('Accept-Ranges: bytes');
    readfile($full_path);
}

exit();
