<?php
session_start(); // Optional

// --- Configuration ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database Credentials (Replace with your actual credentials)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your DB password
define('DB_NAME', 'wynn_fyp'); // Your database name

// --- Dropdown Options (CUSTOMIZE THESE) ---
$privilege_options = ['none', 'view', 'edit', 'all']; // Example privileges
$role_options = ['consultant', 'administrator', 'editor']; // Example roles


// --- Variable Initialization ---
$username = '';
$email = '';
$referral_id = '';
$selected_privilege = '';
$selected_role = '';
$error_message = '';
$success_message = '';

// --- Process Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = trim($_POST['email']);
    $referral_id = trim($_POST['referral_id']); // Now mandatory
    $selected_privilege = trim($_POST['privileges']);
    $selected_role = trim($_POST['roles']);

    // --- Validation (Referral ID is now mandatory) ---
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($referral_id) || empty($selected_privilege) || empty($selected_role)) {
        $error_message = 'All fields are required.'; // Updated error message
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error_message = 'Password must contain at least one uppercase letter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error_message = 'Invalid email format.';
    } elseif (!ctype_digit($referral_id)) { // Check if Referral ID is numeric (since it's mandatory)
         $error_message = 'Referral Admin ID must be a number.';
    } elseif (!in_array($selected_privilege, $privilege_options)) {
        $error_message = 'Invalid privilege selected.';
    } elseif (!in_array($selected_role, $role_options)) {
        $error_message = 'Invalid role selected.';
    } else {
        // --- Database Connection ---
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Add Admin DB Connection Error: " . $conn->connect_error);
            $error_message = "Database connection failed. Please try again later.";
        } else {
            // --- Check for Duplicate Username ---
            $check_sql = "SELECT Admin_ID FROM admin_file WHERE Admin_Login_Name = ? OR Email = ?";
            $check_stmt = $conn->prepare($check_sql);
            if ($check_stmt) {
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_stmt->store_result();
            
                if ($check_stmt->num_rows > 0) {
                    $error_message = "An admin with this username or email already exists. Please use a different one.";        
                } else {
                     // --- Check if Referral Admin ID exists (Now always checked as it's mandatory) ---
                     $referrer_exists = false; // Assume false until proven true
                     $ref_check_sql = "SELECT Admin_ID FROM admin_file WHERE Admin_ID = ?";
                     $ref_stmt = $conn->prepare($ref_check_sql);
                     if($ref_stmt){
                         $ref_stmt->bind_param("i", $referral_id);
                         $ref_stmt->execute();
                         $ref_stmt->store_result();
                         if($ref_stmt->num_rows > 0){
                             $referrer_exists = true; // Referrer found
                         } else {
                             $error_message = "The specified Referral Admin ID does not exist.";
                         }
                         $ref_stmt->close();
                     } else {
                         $error_message = "Error checking referral ID.";
                         error_log("Referral Check Prepare Error: " . $conn->error);
                     }

                    // --- Proceed only if username is unique AND referrer exists ---
                    if($referrer_exists) {
                        // Hash the password securely
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        // Referral ID is now always an integer
                        $referral_id_to_insert = (int)$referral_id;

                        // Prepare INSERT statement including Referral_Admin_ID
                        $sql = "INSERT INTO admin_file (Admin_Login_Name, Password, Email, Referral_Admin_ID, Privileges, Roles, Add_Time, Change_Timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                        $stmt = $conn->prepare($sql);

                        if ($stmt) {
                            // Bind parameters (s = string, i = integer)
                            $stmt->bind_param("sssiss",
                                $username,
                                $hashed_password,
                                $email,
                                $referral_id_to_insert,
                                $selected_privilege,
                                $selected_role
                            );

                            // Execute statement
                            if ($stmt->execute()) {
                                $success_message = "Admim '".htmlspecialchars($username)."' added successfully!";
                                // Clear form fields on success
                                $username = '';
                                $email = '';
                                $referral_id = '';
                                $selected_privilege = '';
                                $selected_role = '';
                            } else {
                                error_log("Add Admin Execute Error: " . $stmt->error);
                                $error_message = 'Failed to add admin user. Please try again.';
                            }
                            $stmt->close();
                        } else {
                            error_log("Add Admin Prepare Error: " . $conn->error);
                            $error_message = 'Failed to prepare adding admin user. Please try again.';
                        }
                    } // end if referrer_exists
                }
                $check_stmt->close();
            } else {
                 error_log("Add Admin Check Prepare Error: " . $conn->error);
                 $error_message = 'Failed to check username validity. Please try again.';
            }
            $conn->close();
        } // End database connection else
    } // End validation else
} // End POST request processing

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinSight – Add New Admin</title>
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
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(10, 37, 64, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(0, 123, 255, 0.03) 0%, transparent 50%);
        }
        
        /* Container */
        .container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }
        
        /* Header */
        h2 {
            color: #0A2540;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #00c6ff);
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
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 650px;
            margin: 0 auto;
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
        
        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #0A2540;
            font-size: 0.95rem;
        }
        
        .input-hint {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
            display: block;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 1px solid #E9ECEF;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
            background-color: #fff;
        }
        
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236c757d'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5rem;
        }
        
        button[type="submit"] {
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
            margin-top: 2rem;
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
        
        button[type="submit"] i {
            margin-right: 0.5rem;
        }
        
        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 2rem;
        }
        
        .back-link a {
            display: inline-flex;
            align-items: center;
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link a i {
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .back-link a:hover {
            color: #0A2540;
        }
        
        .back-link a:hover i {
            transform: translateX(-3px);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                margin: 2rem auto;
            }
            
            form {
                padding: 1.75rem;
            }
            
            h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Admin User</h2>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" novalidate>
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Admin Login Name:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required placeholder="Enter login name">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password:</label>
                <input type="password" id="password" name="password" required pattern="(?=.*[A-Z]).{8,}" placeholder="Enter password">
                <span class="input-hint">Min. 8 characters, at least one uppercase letter.</span>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter email address">
            </div>

            <div class="form-group">
                <label for="referral_id"><i class="fas fa-user-shield"></i> Referral Admin ID:</label> 
                <input type="number" id="referral_id" name="referral_id" value="<?php echo htmlspecialchars($referral_id); ?>" min="1" required placeholder="Enter referrer's admin ID"> 
                <span class="input-hint">Enter the ID of the existing admin who referred this user.</span> 
            </div>

            <div class="form-group">
                <label for="privileges"><i class="fas fa-key"></i> Privileges:</label>
                <select id="privileges" name="privileges" required>
                    <option value="">-- Select Privilege --</option>
                    <?php foreach ($privilege_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($selected_privilege === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="roles"><i class="fas fa-user-tag"></i> Role:</label>
                <select id="roles" name="roles" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach ($role_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($selected_role === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit"><i class="fas fa-user-plus"></i> Add Admin</button>
        </form>

        <div class="back-link">
            <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>