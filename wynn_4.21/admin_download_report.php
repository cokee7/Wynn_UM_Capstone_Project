<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);
ini_set('memory_limit', '512M');

// --- Authentication Check ---
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access Denied. Please log in.";
    exit();
}

define('SCRIPT_DIR', __DIR__);
$tcpdfPath = SCRIPT_DIR . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    $errorMsg = "TCPDF library not found at: " . htmlspecialchars($tcpdfPath);
    error_log($errorMsg);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Server Configuration Error: PDF library missing.";
    exit;
}
require_once($tcpdfPath);

require_once 'admin_db_connect.php';

$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$report_id) {
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid Report ID.";
    exit();
}

// --- Fetch report content ---
$stmt = $conn->prepare("SELECT Report_Content, Topic, Generated_Time FROM report_file WHERE Report_ID = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    header('HTTP/1.1 404 Not Found');
    echo "Report not found.";
    exit();
}

$row = $result->fetch_assoc();
$content = $row['Report_Content'];
$topic = $row['Topic'] ?? 'General';
$generated_time_raw = $row['Generated_Time'] ?? null;

$conn->close();

// --- Format display time ---
$generated_time_display = "N/A";
if ($generated_time_raw) {
    try {
        $date = new DateTime($generated_time_raw);
        $generated_time_display = $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $generated_time_display = "Invalid Date";
    }
}

// --- PDF Config ---
$pdfConfig = [
    'orientation'   => 'P', 'unit' => 'mm', 'format' => 'A4',
    'font'          => 'cid0cs', 'fontSize' => 11, 'fontSizeSubtitle' => 13,
    'marginLeft'    => 15, 'marginRight' => 15, 'marginTop' => 20, 'marginBottom' => 25,
    'reportTitle'   => 'FinSight Report: ' . htmlspecialchars($topic),
    'pdfAuthor'     => 'FinSight Admin',
    'pdfTitle'      => 'FinSight Admin Report - ' . $topic,
    'pdfSubject'    => 'Admin Report for Topic: ' . $topic,
    'pdfKeywords'   => 'finance, insight, admin, report, ' . $topic,
];

// --- Generate PDF ---
try {
    $pdf = new TCPDF($pdfConfig['orientation'], $pdfConfig['unit'], $pdfConfig['format'], true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfConfig['pdfAuthor']);
    $pdf->SetTitle($pdfConfig['pdfTitle']);
    $pdf->SetSubject($pdfConfig['pdfSubject']);
    $pdf->SetKeywords($pdfConfig['pdfKeywords']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFooterMargin(15);
    $pdf->SetMargins($pdfConfig['marginLeft'], $pdfConfig['marginTop'], $pdfConfig['marginRight']);
    $pdf->SetAutoPageBreak(TRUE, $pdfConfig['marginBottom']);
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
    $pdf->AddPage();

    // Title
    $pdf->SetFont($pdfConfig['font'], 'B', $pdfConfig['fontSize'] + 4);
    $pdf->Cell(0, 10, $pdfConfig['reportTitle'], 0, 1, 'C');
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
    $pdf->Ln(2);

    // Timestamp
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize'] - 1);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, 'Generated on: ' . $generated_time_display, 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
    $pdf->Ln(6);

    // Format content
    $content_parts = preg_split('/(\*\*.+?\*\*)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $isFirst = true;

    foreach ($content_parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        if (preg_match('/^\*\*(.+)\*\*$/', $part, $matches)) {
            if (!$isFirst) $pdf->Ln(5);
            $pdf->SetFont($pdfConfig['font'], 'B', $pdfConfig['fontSizeSubtitle']);
            $pdf->MultiCell(0, 0, $matches[1], 0, 'L', 0, 1);
            $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
            $pdf->Ln(3);
        } else {
            $pdf->MultiCell(0, 0, $part, 0, 'J', 0, 1);
            $pdf->Ln(5);
        }
        $isFirst = false;
    }

    // File Name
    $safe_topic = preg_replace('/[^a-zA-Z0-9_\-\p{L}]/u', '_', $topic);
    $safe_topic = trim($safe_topic, '_');
    $safe_topic = mb_substr($safe_topic, 0, 50, 'UTF-8');
    $filename = "FinSight_Admin_Report_" . ($safe_topic ?: 'General') . "_" . date('Ymd_His') . ".pdf";

    // Output PDF
    while (ob_get_level()) ob_end_clean();
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    error_log("TCPDF Admin Error (Report ID: {$report_id}): " . $e->getMessage());
    header("Content-Type: text/plain; charset=utf-8");
    echo "Error: PDF generation failed.";
    exit;
}
?>
