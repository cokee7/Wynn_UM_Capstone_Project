<?php
// logout.php

// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the homepage after 3 seconds
// header("Location: index.html");
// exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinSight – Logging Out</title>
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Header */
        header {
            background-color: #0A2540;
            color: #fff;
            padding: 1.5rem;
            text-align: center;
        }

        header h1 {
            font-weight: 600;
            font-size: 2rem;
        }

        /* Main Content */
        .logout-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 1.5rem;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .logout-icon {
            color: #007bff;
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .logout-message {
            font-size: 1.5rem;
            color: #0A2540;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .logout-subtext {
            font-size: 1rem;
            color: #555;
            margin-bottom: 2rem;
        }

        .redirect-info {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .back-home-button {
            display: inline-block;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            padding: 1rem 2.5rem;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            border: none;
            font-size: 1rem;
        }

        .back-home-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        /* Progress Indicator */
        .progress-container {
            width: 100%;
            max-width: 300px;
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 2rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            width: 0;
            background-color: #007bff;
            border-radius: 3px;
            transition: width 3s ease-in-out;
        }

        /* Footer */
        footer {
            background-color: #0A2540;
            color: #ADB5BD;
            text-align: center;
            padding: 1.5rem;
            font-size: 0.9rem;
        }
    </style>
    <script>
        // Redirect after 3 seconds
        window.onload = function() {
            // Start progress bar animation
            setTimeout(function() {
                document.querySelector('.progress-bar').style.width = '100%';
            }, 100);
            
            // Redirect to homepage
            setTimeout(function() {
                window.location.href = "index.html";
            }, 3000);
        };
    </script>
</head>
<body>
    <header>
        <h1>FinSight</h1>
    </header>

    <div class="logout-container">
        <i class="fas fa-check-circle logout-icon"></i>
        <h2 class="logout-message">You have been logged out successfully</h2>
        <p class="logout-subtext">Thank you for using FinSight. We look forward to seeing you again soon.</p>
        <p class="redirect-info">You will be redirected to the homepage in a few seconds...</p>
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        <p style="margin-top: 2rem;">
            <a href="index.html" class="back-home-button">Back to Home</a>
        </p>
    </div>

    <footer>
        <div class="footer-copyright">
            © 2025 FinSight Technologies. All rights reserved.
        </div>
    </footer>
</body>
</html>