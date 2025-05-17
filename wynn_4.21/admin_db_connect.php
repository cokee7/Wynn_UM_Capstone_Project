<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "wynn_fyp";

// --- Use Exception Handling ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable mysqli exceptions

$conn = null; // Initialize $conn to null

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4"); // Set charset only on successful connection
} catch (mysqli_sql_exception $e) {
    // Connection failed. $conn remains null.
    // Optionally log the error securely:
    // error_log("Database connection failed: " . $e->getMessage());
    // Do NOT echo or die here.
}

// Reset reporting to default if needed elsewhere, though often not necessary
// mysqli_report(MYSQLI_REPORT_OFF);

?>