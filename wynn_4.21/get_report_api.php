<?php
// get_report_api.php
session_start();
header('Content-Type: application/json');

// --- Database credentials ---
$servername = 'localhost';
$username   = 'root';
$password   = '';
$dbname     = 'wynn_fyp';

$topic = isset($_GET['topic']) 
    ? trim($_GET['topic']) 
    : '';

if ($topic === '') {
    echo json_encode(['error' => 'No topic specified.']);
    exit;
}

// --- Connect ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connect failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

// --- Fetch latest report for this topic ---
$sql = "SELECT Report_Content, Generated_Time
        FROM report_file
       WHERE Topic = ?
       ORDER BY Generated_Time DESC, Report_ID DESC
       LIMIT 1";
$stmt = $conn->prepare($sql);
if (! $stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('s', $topic);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'topic'          => $topic,
        'content'        => $row['Report_Content'],
        'generated_time' => $row['Generated_Time']
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => "No report found for '{$topic}'."]);
}

$stmt->close();
$conn->close();
