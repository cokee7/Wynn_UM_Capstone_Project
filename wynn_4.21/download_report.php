<?php
// download_report.php - Generates and forces download of a specific topic report as PDF

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional log file path

set_time_limit(300);
ini_set('memory_limit', '512M');

define('SCRIPT_DIR', __DIR__);
$tcpdfPath = SCRIPT_DIR . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    $errorMsg = "TCPDF library not found at: " . htmlspecialchars($tcpdfPath); error_log($errorMsg);
    header("Content-Type: text/plain; charset=utf-8"); echo "Server Configuration Error: PDF library missing."; exit;
}
require_once($tcpdfPath);

$topic = isset($_GET['topic']) ? trim(urldecode($_GET['topic'])) : '';
$page_title_topic = $topic ? htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') : 'Report';

// --- Database Configuration ---
$db_user = 'root'; $db_pass = ''; $db_name = 'wynn_fyp';
$db_socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$db_host = null; $db_port = null;

// --- PDF Config ---
$pdfConfig = [
    'orientation'   => 'P', 'unit' => 'mm', 'format' => 'A4',
    'font'          => 'cid0cs', 'fontSize' => 11, 'fontSizeSubtitle' => 13,
    'marginLeft'    => 15, 'marginRight' => 15, 'marginTop' => 20, 'marginBottom' => 25,
    'reportTitle'   => 'FinSight Report: ' . $page_title_topic,
    'pdfAuthor'     => 'FinSight Generator', 'pdfTitle' => 'FinSight Market Analysis Report - ' . $page_title_topic,
    'pdfSubject'    => 'Market Analysis for topic: ' . $page_title_topic,
    'pdfKeywords'   => 'market analysis, report, finance, insight, ' . $page_title_topic,
];

// --- Fetch Report Content & Time ---
$raw_content = null;
$generated_time_raw = null; // Variable to store raw time from DB
$generated_time_display = "N/A"; // Formatted time for display
$conn = null;
$report_found = false;

if (empty($topic)) {
    header("Content-Type: text/plain; charset=utf-8"); echo "Error: No topic specified for download."; exit;
}

try {
    $conn = mysqli_connect(($db_socket ? null : $db_host), $db_user, $db_pass, $db_name, ($db_socket ? null : $db_port), $db_socket);
    if (!$conn) { throw new Exception("DB connection failed: " . @mysqli_connect_error()); }
    if (!mysqli_set_charset($conn, "utf8mb4")) { throw new Exception("DB charset error: " . mysqli_error($conn)); }

    $sql = "SELECT Report_Content, Generated_Time -- Fetch time as well
            FROM report_file WHERE Topic = ?
            ORDER BY Generated_Time DESC, Report_ID DESC LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { throw new Exception("DB prepare failed: " . mysqli_error($conn)); }

    mysqli_stmt_bind_param($stmt, "s", $topic);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $raw_content = $row['Report_Content'];
        $generated_time_raw = $row['Generated_Time']; // Store raw time
        $report_found = true;
        mysqli_free_result($result);

        // Format Generation Time here
        if ($generated_time_raw) {
            try {
                $date = new DateTime($generated_time_raw);
                $generated_time_display = $date->format('Y-m-d H:i:s'); // Format as needed
            } catch (Exception $e) { $generated_time_display = "Invalid Date"; }
        } else { $generated_time_display = "Not Available"; }

    } else { error_log("Notice: No report found for topic '{$topic}' (download)."); }
    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    error_log("Error fetching report data (Topic: {$topic}, download): " . $e->getMessage());
    $raw_content = null;
} finally { if ($conn) { mysqli_close($conn); } }

// Stop if no content
if (!$report_found || $raw_content === null || trim($raw_content) === '') {
    header("Content-Type: text/plain; charset=utf-8");
    echo "Error: No report content found for topic ('" . $page_title_topic . "').";
    exit;
}

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

    // --- Add Report Title ---
    if (!empty($pdfConfig['reportTitle'])) {
        $pdf->SetFont($pdfConfig['font'], 'B', $pdfConfig['fontSize'] + 4);
        $pdf->Cell(0, 10, $pdfConfig['reportTitle'], 0, 1, 'C'); // Reduced height slightly
        $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
        $pdf->Ln(2); // Reduced space after title
    }

    // --- Add Generated Time ---
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize'] - 1); // Slightly smaller font for time
    $pdf->SetTextColor(100, 100, 100); // Grey color for time
    $pdf->Cell(0, 8, 'Generated on: ' . $generated_time_display, 0, 1, 'C'); // Centered time
    $pdf->SetTextColor(0, 0, 0); // Reset text color
    $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']); // Reset font size
    $pdf->Ln(6); // Add space after the time
    // --- End Generated Time ---


    // --- Write Report Content with Subtitle Formatting & Spacing ---
    $content_parts = preg_split('/(\*\*.+?\*\*)/s', $raw_content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    if ($content_parts === false) {
         error_log("Regex error splitting content (download_report.php) for topic '{$topic}'");
         $pdf->MultiCell(0, 0, $raw_content, 0, 'J', 0, 1, '', '', true, 0, false, true, 0, 'T');
    } else {
        $isFirstElement = true; // Flag to handle margin before first element
        foreach ($content_parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (substr($part, 0, 2) === '**' && substr($part, -2) === '**') {
                // --- Subtitle ---
                $subtitle_text = trim(substr($part, 2, -2));
                // Add space *before* subtitle, unless it's the very first thing
                if (!$isFirstElement) {
                    $pdf->Ln(5); // Space before subtitle
                }
                $pdf->SetFont($pdfConfig['font'], 'B', $pdfConfig['fontSizeSubtitle']);
                $pdf->MultiCell(0, 0, $subtitle_text, 0, 'L', 0, 1, '', '', true, 0, false, true, 0, 'T');
                $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
                $pdf->Ln(3); // Small space after subtitle text
                $isFirstElement = false;

            } else {
                // --- Regular content ---
                // Add space *before* paragraph, unless it's the very first thing or follows a subtitle closely
                 if (!$isFirstElement) {
                     // $pdf->Ln(1); // Minimal space maybe needed if previous was subtitle
                 }
                $pdf->SetFont($pdfConfig['font'], '', $pdfConfig['fontSize']);
                $pdf->MultiCell(0, 0, $part, 0, 'J', 0, 1, '', '', true, 0, false, true, 0, 'T');
                // Increase space *after* regular content block to create paragraph spacing
                $pdf->Ln(5); // <<< INCREASED SPACE BETWEEN PARAGRAPHS
                $isFirstElement = false;
            }
        }
    }

    // --- Prepare Filename ---
    $safe_topic_name = preg_replace('/[^a-zA-Z0-9_\-\p{L}]/u', '_', $topic);
    $safe_topic_name = trim($safe_topic_name, '_');
    $safe_topic_name = mb_substr($safe_topic_name, 0, 50, 'UTF-8');
    $filename = "FinSight_Report_" . ($safe_topic_name ?: 'General') . "_" . date('Ymd') . ".pdf";

    // --- Clear Output Buffer & Send PDF ---
    while (ob_get_level()) { ob_end_clean(); }
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    error_log("TCPDF Generation Error (Topic: {$topic}, download): " . $e->getMessage() . "\n" . $e->getTraceAsString());
    header("Content-Type: text/plain; charset=utf-8");
    echo "Error: Failed to generate PDF report.";
    exit;
}
?>