<?php
// save_customization.php
// Example code for saving user dashboard preferences.

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Retrieve the user ID from the form or session.
$user_id = $_POST['user_id'] ?? null;
if (!$user_id) {
    die("User ID missing.");
}

// Retrieve preferences from the form.
// Use the correct input names: 'chart_types[]'
$chart_types = $_POST['chart_types'] ?? [];  // This is an array of selected chart types.

// Convert the chart_types array to a comma-separated string.
$chart_types_str = implode(',', $chart_types);

// Connect to the database using the defined variable names.
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "wynn_fyp";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Insert or update the user's preferences.
// Use the correct column names: visible_chart_types
$sql = "
    INSERT INTO user_dashboard_preferences (User_ID, visible_chart_types)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE 
        visible_chart_types = VALUES(visible_chart_types),
        updated_at   = NOW()
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param("is", $user_id, $chart_types_str);
$stmt->execute();

if ($stmt->error) {
    die("Error saving preferences: " . $stmt->error);
}

$stmt->close();
$conn->close();

// Redirect back to the dashboard (or to a success page)
header("Location: dashboard.php?user_id=$user_id");
exit();
?>
