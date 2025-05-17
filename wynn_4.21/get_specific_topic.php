<?php
session_start();
header('Content-Type: application/json');

// --- Database credentials ---
$servername = 'localhost';
$username   = 'root';
$password   = '';
$dbname     = 'wynn_fyp';

// --- Read optional days parameter (1–30) ---
$days_to_fetch = isset($_GET['days'])
    ? max(1, min(30, (int)$_GET['days']))
    : 7;

// Compute cutoff datetime
$date_limit = date('Y-m-d H:i:s', strtotime("-{$days_to_fetch} days"));

// --- Connect to MySQL ---
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

// --- Get and validate topic parameter ---
if (empty($_GET['topic'])) {
    echo json_encode([]);
    $conn->close();
    exit;
}
$topic = trim($_GET['topic']);

// --- Prepare and execute the query ---
$sql = "
    SELECT
      Title,
      Link,
      DATE_FORMAT(Created_Time, '%Y-%m-%d %H:%i') AS Created_Time
    FROM topics_file
   WHERE Content LIKE ?
     AND Created_Time >= ?
   ORDER BY Created_Time DESC
";

$stmt = $conn->prepare($sql);
if (! $stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare query: ' . $conn->error]);
    $conn->close();
    exit;
}

$searchTerm = "%{$topic}%";
$stmt->bind_param("ss", $searchTerm, $date_limit);
if (! $stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Query execution failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = [
        'Title'        => $row['Title'],
        'Link'         => $row['Link'],
        'Created_Time' => $row['Created_Time']
    ];
}

// --- Clean up and return ---
$stmt->close();
$conn->close();

echo json_encode($entries, JSON_PRETTY_PRINT);
