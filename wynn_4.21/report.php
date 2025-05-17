<?php
// report.php - Displays a specific topic report from the database

// --- Database Configuration ---
// !! IMPORTANT: Use values from your config or secure method !!
$db_user = 'root';
$db_pass = '';
$db_name = 'wynn_fyp';
$db_socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';


// --- Get Topic from URL ---
$topic = isset($_GET['topic']) ? trim(urldecode($_GET['topic'])) : '';
$page_title_topic = $topic ? htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') : 'Report'; // For display

// --- Initialize Variables ---
$report_content_html = "<p class='info-message'>No topic specified or report found for this topic.</p>"; // Default message
$generated_time_display = "N/A";
$report_found = false;

// --- Establish Database Connection ---
$conn = mysqli_connect(($db_socket ? null : $db_host), $db_user, $db_pass, $db_name, ($db_socket ? null : 3306), $db_socket);

// Check connection
if (!$conn) {
    error_log("Database Connection Error in report.php: " . mysqli_connect_error());
    $report_content_html = "<p class='error-message'>Error: Unable to connect to the database.</p>";
} else {
    mysqli_set_charset($conn, "utf8mb4");

    if (!empty($topic)) {
        // --- Query for the latest report for the SPECIFIC topic ---
        $sql = "SELECT Report_Content, Generated_Time
                FROM report_file
                WHERE Topic = ?
                ORDER BY Generated_Time DESC, Report_ID DESC
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $topic);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $raw_content = $row['Report_Content'];
                $generated_time_raw = $row['Generated_Time'];
                $report_found = true;

                // --- NEW: Prepare content with <p> and <h3> tags ---
                if ($raw_content) {
                    // 1. Escape HTML special chars from the raw content FIRST for security
                    $escaped_content = htmlspecialchars($raw_content, ENT_QUOTES, 'UTF-8');

                    // 2. Replace **subtitle** with <h3>subtitle</h3>
                    //    Use a placeholder temporarily if needed, or do it carefully
                    $content_with_headings = preg_replace('/\*\*(.*?)\*\*/s', '<h3>$1</h3>', $escaped_content);

                    // 3. Split into lines/blocks (handle Windows/Unix newlines)
                    $lines = preg_split('/(\r\n|\r|\n)/', $content_with_headings);

                    // 4. Process lines into paragraphs and headings
                    $html_output = "";
                    $current_paragraph = "";
                    foreach ($lines as $line) {
                        $trimmed_line = trim($line);
                        if (empty($trimmed_line)) {
                            // Empty line signifies a potential paragraph break
                            if (!empty($current_paragraph)) {
                                $html_output .= "<p>" . trim($current_paragraph) . "</p>\n"; // Close previous paragraph
                                $current_paragraph = "";
                            }
                        } elseif (substr($trimmed_line, 0, 4) === '<h3>' && substr($trimmed_line, -5) === '</h3>') {
                            // It's a heading line
                            if (!empty($current_paragraph)) {
                                $html_output .= "<p>" . trim($current_paragraph) . "</p>\n"; // Close paragraph before heading
                                $current_paragraph = "";
                            }
                            $html_output .= $trimmed_line . "\n"; // Add the heading directly
                        } else {
                            // Non-empty, non-heading line: add to current paragraph buffer
                            $current_paragraph .= $trimmed_line . " "; // Add space between joined lines
                        }
                    }
                    // Add any remaining text as the last paragraph
                    if (!empty($current_paragraph)) {
                         $html_output .= "<p>" . trim($current_paragraph) . "</p>\n";
                    }

                    $report_content_html = $html_output; // Assign the generated HTML

                } else {
                    $report_content_html = "<p class='info-message'>Report content is empty.</p>";
                }
                // --- End NEW content processing ---


                // --- Format Time ---
                if ($generated_time_raw) {
                    try { $date = new DateTime($generated_time_raw); $generated_time_display = $date->format('Y-m-d H:i:s'); }
                    catch (Exception $e) { $generated_time_display = "Invalid Date"; }
                } else { $generated_time_display = "Not Available"; }

                mysqli_free_result($result);
            } else {
                 $report_content_html = "<p class='info-message'>No report found for the topic: '" . htmlspecialchars($topic, ENT_QUOTES, 'UTF-8') . "'.</p>";
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("SQL Prepare Error in report.php: " . mysqli_error($conn));
            $report_content_html = "<p class='error-message'>Error: Could not retrieve the report.</p>";
        }
    } else {
        $report_content_html = "<p class='info-message'>Please select a topic to view its report.</p>";
    }
    mysqli_close($conn);
}

// Update download link
$download_link = "#";
if (!empty($topic)) {
    $download_link = "download_report.php?topic=" . urlencode($topic);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FinSight – Report Summary</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Basic Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: #f8f9fa;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(10, 37, 64, 0.02) 0%, transparent 60%),
                radial-gradient(circle at 85% 85%, rgba(0, 123, 255, 0.03) 0%, transparent 60%);
        }

        /* Header */
        header {
            background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
            color: #fff;
            padding: 1.5rem 2rem;
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
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
            text-align: center;
            margin-bottom: 0.75rem;
            font-weight: 600;
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
            width: 100%;
        }

        header h1::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 2px;
            background-color: rgba(255, 255, 255, 0.5);
        }

        .header-nav {
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
            margin-top: 0.75rem;
        }

        .header-nav a {
            text-decoration: none;
            background-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.85);
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            display: flex;
            align-items: center;
        }

        .header-nav a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .header-nav a i {
            margin-right: 0.5rem;
        }

        /* Main Content */
        main {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .report-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
            position: relative;
        }

        .report-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #007bff 0%, #0056b3 100%);
            border-radius: 12px 0 0 12px;
        }

        .report-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .report-header h2 {
            color: #0A2540;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .report-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .download-section {
            background: linear-gradient(90deg, #f8f9fa 0%, #f2f5fa 100%);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .download-btn {
            background: linear-gradient(90deg, #007bff 0%, #0069d9 100%);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
        }

        .download-btn:hover {
            background: linear-gradient(90deg, #0062cc 0%, #004494 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
        }

        .report-content {
            color: #444;
            line-height: 1.8;
        }

                /* Add space BELOW each paragraph */
        .report-content p {
            margin-bottom: 2em; 
        }

        .report-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .report-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .report-section h3 {
            color: #0A2540;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }

        /* Footer */
        footer {
            background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
            color: #ADB5BD;
            text-align: center;
            padding: 1.5rem;
            position: relative;
            font-size: 0.9rem;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            main {
                padding: 1rem;
            }

            .report-container {
                padding: 1.5rem;
            }

            .download-section {
                flex-direction: column;
                align-items: stretch;
            }

            .download-btn {
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

  <!-- Header -->
  <header>
      <h1>FinSight Report: <?php echo $page_title_topic; ?></h1>
      <?php if ($report_found): ?>
          <div class="header-nav">
              <span>Generated on: <strong><?php echo $generated_time_display; ?></strong></span>
          </div>
      <?php endif; ?>
      <div class="header-nav">
          <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
          <a href="<?php echo $download_link; ?>" <?php if (!$report_found) echo 'style="display:none;"';?>><i class="fas fa-download"></i> Download</a>
          <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
  </header>

  <!-- Main Content -->
  <main>
    <div class="report-container">
      <div class="report-content">
        <?php echo $report_content_html; ?>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer>
    <p>© <?php echo date("Y"); ?> FinSight. All rights reserved.</p>
  </footer>

</body>
</html>