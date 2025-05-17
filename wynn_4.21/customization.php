<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start session for feedback messages

require_once 'admin_db_connect.php'; // Adjust path if needed

// Attempt to get User ID from GET first, fall back to session
$user_id = null;
if (isset($_GET['user_id']) && filter_var($_GET['user_id'], FILTER_VALIDATE_INT)) {
    $user_id = (int)$_GET['user_id'];
} elseif (isset($_SESSION['user_id']) && filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)) {
    $user_id = (int)$_SESSION['user_id'];
} else {
    error_log("Customization page access attempt without valid User ID.");
    die("Error: User ID not specified or invalid. Please access via your dashboard.");
}

// --- Initialize variables ---
$current_prefs = [
    'visible_chart_types' => ['line', 'bar', 'scatter'], // Default charts (removed violin due to compatibility issues)
];
$message = '';
$message_type = ''; // 'success' or 'error'
$fetch_error = null;

// --- Check for feedback messages from save action ---
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    $message_type = $_SESSION['form_message_type'] ?? 'error'; // Default to error if type not set
    unset($_SESSION['form_message']); // Clear message after retrieving
    unset($_SESSION['form_message_type']);
}

// --- Fetch Current Preferences ---
if (!$conn) {
    $fetch_error = 'Database connection object not created.';
    error_log("Customization Error: db_connect.php failed to provide a connection object.");
} elseif ($conn->connect_error) {
    $fetch_error = 'Database connection failed: ' . $conn->connect_error;
    error_log("Customization DB Connection Error: " . $conn->connect_error);
} else {
    $stmt_fetch = $conn->prepare("SELECT visible_chart_types FROM user_dashboard_preferences WHERE User_ID = ?");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $user_id);
        $stmt_fetch->execute();
        $result = $stmt_fetch->get_result();
        if ($pref_data = $result->fetch_assoc()) {
            // Since visible_chart_types is stored as a comma-separated string,
            // we split it into an array.
            $charts_from_db = $pref_data['visible_chart_types'] ?? '';
            if (!empty($charts_from_db)) {
                $decoded_charts = explode(',', $charts_from_db);
                // Trim any extra whitespace from each chart type.
                $decoded_charts = array_map('trim', $decoded_charts);
                $current_prefs['visible_chart_types'] = $decoded_charts;
            } else {
                error_log("Customization Warning: No chart preferences found for User_ID $user_id. Using defaults.");
            }

        } else {
            // No prefs found, defaults are in effect.
            // echo "<!-- DEBUG: No preferences found for user $user_id -->";
        }
        $stmt_fetch->close();
    } else {
        $fetch_error = "Error preparing preferences statement: " . $conn->error;
        error_log("Customization Error preparing preferences statement for User_ID $user_id: " . $conn->error);
    }
    $conn->close(); // Close connection after fetching
}

// If there was a fetch error, display it (if no other message exists)
if ($fetch_error && !$message) {
    $message = "Warning: Could not load current preferences ({$fetch_error}). Displaying defaults.";
    $message_type = "error";
}

// --- Helper functions for form ---
function isChartSelected($type, $selected_array) {
    return in_array($type, $selected_array);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>FinSight – Dashboard Customization</title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* Basic Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    /* Modern Font & Base Styling */
    body {
      font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      color: #333;
      background-color: #f8f9fa;
      line-height: 1.7;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      background-image: 
        radial-gradient(circle at 25% 25%, rgba(10, 37, 64, 0.02) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(0, 123, 255, 0.03) 0%, transparent 50%);
    }

    /* Header */
    header {
      background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
      color: #fff;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      position: relative;
      z-index: 10;
      text-align: center;
    }

    header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
    }

    header h1 {
      margin: 0;
      font-weight: 600;
      font-size: 1.8rem;
      letter-spacing: 0.5px;
    }

    /* Main Container */
    .container {
      max-width: 800px;
      margin: 3rem auto;
      padding: 0 1.5rem;
      flex: 1;
      text-align: center;
    }

    /* Navigation Buttons */
    .top-buttons {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2rem;
    }

    .top-buttons a {
      background: linear-gradient(90deg, #007bff, #0069d9);
      color: #fff;
      text-decoration: none;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
      display: flex;
      align-items: center;
    }

    .top-buttons a i {
      margin-right: 0.5rem;
    }

    .top-buttons a:hover {
      transform: translateY(-2px);
      background: linear-gradient(90deg, #0062cc, #004494);
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }

    /* Message Styles */
    .message {
      padding: 1.25rem;
      margin-bottom: 2rem;
      border-radius: 8px;
      border: 1px solid transparent;
      text-align: center;
      font-weight: 500;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .message::before {
      font-family: "Font Awesome 5 Free";
      font-weight: 900;
      margin-right: 1rem;
      font-size: 1.2rem;
    }

    .message.success {
      background-color: #d4edda;
      border-color: #c3e6cb;
      color: #155724;
    }

    .message.success::before {
      content: "\f058"; /* fa-check-circle */
      color: #28a745;
    }

    .message.error {
      background-color: #f8d7da;
      border-color: #f5c6cb;
      color: #721c24;
    }

    .message.error::before {
      content: "\f057"; /* fa-times-circle */
      color: #dc3545;
    }

    /* Form Styling */
    form {
      width: 100%;
      max-width: 600px;
      text-align: left;
      margin: 0 auto;
      background: #fff;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      border: 1px solid #E9ECEF;
      position: relative;
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    form:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    form::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
    }

    form p {
      text-align: center;
      margin-bottom: 2rem;
      color: #555;
      font-size: 1.05rem;
    }

    form h3 {
      color: #0A2540;
      margin-bottom: 1.5rem;
      margin-top: 2rem;
      border-bottom: 1px solid #E9ECEF;
      padding-bottom: 0.75rem;
      font-weight: 600;
      font-size: 1.25rem;
      position: relative;
    }

    form h3:first-of-type {
      margin-top: 0;
    }

    form h3::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      width: 40px;
      height: 3px;
      background: linear-gradient(90deg, #007bff, #00c6ff);
    }

    /* Form Controls */
    label {
      display: block;
      margin: 1.25rem 0;
      cursor: pointer;
      font-weight: 500;
      color: #0A2540;
      transition: transform 0.2s ease;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      display: flex;
      align-items: center;
      border: 1px solid transparent;
    }

    label:hover {
      background-color: #f8f9fa;
      border-color: #E9ECEF;
      transform: translateX(3px);
    }

    input[type="checkbox"], input[type="radio"] {
      margin-right: 0.75rem;
      vertical-align: middle;
      transform: scale(1.2);
      cursor: pointer;
      accent-color: #007bff;
    }

    .form-note {
      font-size: 0.85rem;
      color: #6c757d;
      margin-left: 0.5rem;
      font-weight: 400;
    }

    button[type="submit"] {
      display: block;
      width: 100%;
      background: linear-gradient(90deg, #007bff, #0056b3);
      color: #fff;
      border: none;
      padding: 1rem 2rem;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 2.5rem;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
      position: relative;
      overflow: hidden;
    }

    button[type="submit"]::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.6s ease;
    }

    button[type="submit"]:hover::before {
      left: 100%;
    }

    button[type="submit"]:hover {
      background: linear-gradient(90deg, #0062cc, #004494);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }

    /* Footer */
    footer {
      background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
      color: #ADB5BD;
      text-align: center;
      padding: 1.5rem;
      margin-top: 3rem;
      font-size: 0.9rem;
      position: relative;
    }

    footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
      form {
        padding: 1.75rem;
      }
      
      .container {
        margin: 2rem auto;
      }
      
      button[type="submit"] {
        padding: 0.9rem 1.5rem;
      }
    }
  </style>
</head>
<body>

  <header>
    <h1>FinSight Dashboard Customization</h1>
  </header>

  <main class="container">
    <div class="top-buttons">
      <a href="dashboard.php?user_id=<?php echo $user_id; ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

     <?php // Display feedback message if set ?>
     <?php if ($message): ?>
      <div class="message <?php echo htmlspecialchars($message_type); ?>">
          <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form action="save_customization.php" method="post">
      <!-- Hidden field to pass the user ID -->
      <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

      <p>
          Select the charts and data visualizations you wish to see on your dashboard.
      </p>

      <h3>Choose Charts to Display</h3>
      <label>
        <input type="checkbox" name="chart_types[]" value="line"
          <?php if (isChartSelected('line', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Line Chart</span> <span class="form-note"><i class="fas fa-chart-line"></i> Weekly Topic Trends</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="bar"
          <?php if (isChartSelected('bar', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Bar Chart</span> <span class="form-note"><i class="fas fa-chart-bar"></i> Daily Top Topic</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="scatter"
          <?php if (isChartSelected('scatter', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Scatter Plot Chart</span> <span class="form-note"><i class="fas fa-braille"></i> Topic Hotness Distribution</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="pie"
          <?php if (isChartSelected('pie', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Pie Chart</span> <span class="form-note"><i class="fas fa-pie-chart"></i> Topic Distribution</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="word"
          <?php if (isChartSelected('word', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Word Cloud</span> <span class="form-note"><i class="fas fa-cloud"></i> Topic Frequency</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="violin"
          <?php if (isChartSelected('violin', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Topic Distribution Chart</span> <span class="form-note"><i class="fas fa-chart-area"></i> Top Topics Over Time</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="clustered"
          <?php if (isChartSelected('clustered', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Clustered Bar Chart</span> <span class="form-note"><i class="fas fa-layer-group"></i> Topic Comparison</span>
      </label>
      <label>
        <input type="checkbox" name="chart_types[]" value="heatmap"
          <?php if (isChartSelected('heatmap', $current_prefs['visible_chart_types'])) echo 'checked'; ?>>
        <span>Heatmap</span> <span class="form-note"><i class="fas fa-fire"></i> Topic Activity</span>
      </label>

      <button type="submit"><i class="fas fa-save"></i> Save Preferences</button>
    </form>
  </main>

  <footer>
    <div class="footer-copyright">
      © 2025 FinSight Technologies. All rights reserved.
    </div>
  </footer>

</body>
</html>
