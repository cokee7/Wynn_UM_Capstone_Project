<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
session_unset();
session_destroy();

// Start a fresh one (must be called AFTER destroy)
session_start();


require_once 'admin_db_connect.php'; // Include the database connection

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $login_name = trim($_POST['login_name']);
    $password = $_POST['password'];

    if (empty($login_name) || empty($password)) {
        $login_error = "Please enter both login name and password.";
    } else {
        // Prepare statement to prevent SQL injection
        $sql = "SELECT Admin_ID, Admin_Login_Name, Password FROM admin_file WHERE Admin_Login_Name = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $login_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();

                // Verify the password against the stored hash
                if (password_verify($password, $admin['Password'])) {
                    // Password is correct, start the session
                    session_regenerate_id(true); // Regenerate session ID for security
                    $_SESSION['admin_id'] = $admin['Admin_ID'];
                    $_SESSION['admin_name'] = $admin['Admin_Login_Name'];
                    $_SESSION['admin_login_name'] = $admin['Admin_Login_Name'];

                    // Redirect to the admin dashboard
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    // Invalid password
                    $login_error = "Invalid login name or password.";
                }
            } else {
                // No user found with that login name
                $login_error = "Invalid login name or password.";
            }
            $stmt->close();
        } else {
            // Error preparing statement
            $login_error = "Database error. Please try again later.";
            // Log the actual error: error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinSight Admin Login</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
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
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
            min-height: 100vh;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(10, 37, 64, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(0, 123, 255, 0.03) 0%, transparent 50%);
            padding: 2rem;
        }
        
        /* Main Container */
        .login-box {
            background-color: #fff;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid #E9ECEF;
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #007bff, #00c6ff);
        }
        
        .login-box h1 {
            color: #0A2540;
            margin-bottom: 1.75rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 1rem;
            font-size: 1.8rem;
        }
        
        .login-box h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #00c6ff);
            transform: translateX(-50%);
        }
        
        /* Form Styling */
        .login-form .form-group {
            margin-bottom: 1.75rem;
            text-align: left;
            position: relative;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #0A2540;
            font-size: 0.95rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            transition: color 0.3s ease;
        }
        
        .input-with-icon input {
            left: 3.25rem;
            
        }
        .input-with-icon input:focus + i {
            color: #007bff;
        }
        
        .login-form input {
            width: 100%;
            padding: 1rem 2.2rem;
            border: 1px solid #E9ECEF;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .login-form input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
            background-color: #fff;
        }
        
        .login-form button {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(90deg, #007bff, #0056b3);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-form button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }
        
        .login-form button:hover::before {
            left: 100%;
        }
        
        .login-form button:hover {
            background: linear-gradient(90deg, #0062cc, #004494);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
        }
        
        .login-form button i {
            margin-right: 0.5rem;
        }
        
        /* Error Message */
        .error-message {
            color: #d32f2f;
            background-color: #fde8e8;
            padding: 1rem 1.25rem 1rem 3rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: left;
            border: 1px solid #f5c6cb;
            position: relative;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        
        .error-message::before {
            content: '\f057';
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            left: 1.25rem;
            color: #d32f2f;
            font-size: 1.1rem;
        }
        
        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.5rem;
            padding: 0.9rem 2rem;
            width: auto;
            max-width: 420px;
            background: linear-gradient(90deg, #6c757d, #5a6268);
            color: white;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(108, 117, 125, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .back-button i {
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .back-button:hover {
            background: linear-gradient(90deg, #5a6268, #495057);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(108, 117, 125, 0.3);
        }
        
        .back-button:hover i {
            transform: translateX(-3px);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 576px) {
            .login-box {
                padding: 2rem;
            }
            
            body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Admin Login</h1>
        <?php if (!empty($login_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <form action="admin_login.php" method="post" class="login-form">
            <div class="form-group">
                <label for="login_name">Login Name:</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="login_name" name="login_name" required>
                </div>

            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="input-with-icon">
                <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required>
        
                </div>
            </div>
            <button type="submit" name="login"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
    </div>

    <a href="index.html" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Homepage
    </a>
</body>
</html>