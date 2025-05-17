<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// DB connection
$host = "localhost";
$username = "root";
$password = ""; // Replace with your actual DB password if needed
$database = "wynn_fyp";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    // More user-friendly error for production, log the details
    error_log("Database connection failed: " . $conn->connect_error);
    die("❌ Could not connect to the service. Please try again later.");
}

$error_message = null;
$success_message = null;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form input using null coalescing operator
    $first_name = trim($_POST["first_name"] ?? '');
    $last_name = trim($_POST["last_name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? ''; // Don't trim password initially
    $confirm_password = $_POST["confirm_password"] ?? '';
    $gender = $_POST["gender"] ?? '';

    // --- Input Validation ---

    // 1. Check for empty required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password) || empty($gender)) {
        $error_message = "All fields are required!";
    }
    // 2. Validate Email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error_message = "Invalid email format.";
    }
    // 3. Validate Password Length
    elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    }
    // 4. Validate Password Complexity (at least one uppercase)
    elseif (!preg_match('/[A-Z]/', $password)) {
        $error_message = "Password must contain at least one uppercase letter.";
    }
    // 5. Check if Passwords Match
    elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    }
    // --- End Validation ---
    else {
        // All basic validation passed, proceed to database interaction

        // Hash the password *after* validation
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Create username and real name
        $user_login_name = strtolower(preg_replace('/[^a-z0-9]/i', '', $first_name . $last_name)); // Basic username generation
        $real_name = $first_name . " " . $last_name;

        // Check if email or generated username already exists (Optional but recommended)
        $sql_check = "SELECT User_ID FROM user_file WHERE Email = ? OR User_Login_Name = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
             $stmt_check->bind_param("ss", $email, $user_login_name);
             $stmt_check->execute();
             $stmt_check->store_result();
             if ($stmt_check->num_rows > 0) {
                 $error_message = "❌ Email or username already exists.";
             }
             $stmt_check->close();
        } else {
             $error_message = "❌ Error checking existing user: " . $conn->error;
        }

        // Proceed only if no error message so far (including uniqueness check)
        if ($error_message === null) {
            // Prepare SQL INSERT statement for user_file (Removed Identity_Number, Phone_Number)
            $sql = "INSERT INTO user_file (User_Login_Name, Password, Real_Name, Gender, Email, Add_Time)
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                // Bind parameters
                $stmt->bind_param("sssss", $user_login_name, $hashed_password, $real_name, $gender, $email);

                if ($stmt->execute()) {
                  // Registration into user_file successful.
                  // Retrieve the newly inserted user id.
                  $new_user_id = $conn->insert_id;
              
                  // Insert a default record into user_dashboard_preferences for this new user.
                  // The column is now a text (or MySQL SET) field expecting a comma-separated list.
                  // We auto-select the default three chart types.
                  $default_chart_types = "line"; // Defaults: auto-select "line", "bar", and "polar"
                  $sql_pref = "INSERT INTO user_dashboard_preferences (User_ID, visible_chart_types) VALUES (?, ?)";
                  $stmt_pref = $conn->prepare($sql_pref);
                  if ($stmt_pref) {
                      // "i" for integer, "ss" for the two string values.
                      $stmt_pref->bind_param("is", $new_user_id, $default_chart_types);
                      if (!$stmt_pref->execute()) {
                          error_log("Failed to insert default dashboard preferences for user ID $new_user_id: " . $stmt_pref->error);
                      }
                      $stmt_pref->close();
                  } else {
                      error_log("Failed to prepare dashboard preferences insert for user ID $new_user_id: " . $conn->error);
                  }
              
                  $success_message = "✅ Registration successful! You will be directed to the login page.";
                  // Optionally clear form fields or redirect.
                  // header("Location: login.php");
                  // exit();
                } else {
                    // Log detailed error for admin, show generic message to user
                    error_log("Registration failed for email $email: " . $stmt->error);
                    $error_message = "❌ Registration failed. Please try again later.";
                    // Check for duplicate entry
                    if ($conn->errno == 1062) { // Error code for duplicate entry
                         $error_message = "❌ This email or username is already registered.";
                    }
                }
                $stmt->close();
            } else {
                // Log detailed error for admin
                error_log("SQL prepare error for registration: " . $conn->error);
                $error_message = "❌ An error occurred during registration preparation. Please try again.";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FinSight – Register</title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* Basic Reset */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    /* Body */
    body { 
      font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
      background-color: #f8f9fa; 
      color: #333;
      line-height: 1.7;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    
    /* Header */
    header { 
      background-color: #0A2540; 
      color: white; 
      padding: 1.5rem; 
      text-align: center; 
    }
    
    header h1 {
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    
    nav { 
      display: flex; 
      justify-content: flex-end; 
      gap: 1rem; 
      margin-top: 0.8rem; 
    }
    
    nav a { 
      color: rgba(255, 255, 255, 0.85); 
      text-decoration: none; 
      font-weight: 500;
      padding: 0.3rem 0.8rem;
      border-radius: 6px;
      transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
    }
    
    nav a:hover { 
      color: #fff;
      background-color: rgba(255, 255, 255, 0.1);
      transform: translateY(-2px);
    }
    
    /* Main Content */
    main { 
      display: flex; 
      justify-content: center; 
      align-items: center; 
      padding: 2rem 1rem; 
      min-height: calc(100vh - 170px); 
    }
    
    .register-container { 
      background: white; 
      padding: 2.5rem; 
      border-radius: 8px; 
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); 
      width: 100%; 
      max-width: 480px;
      border: 1px solid #E9ECEF;
    }
    
    h2 { 
      color: #0A2540; 
      margin-bottom: 1.5rem; 
      text-align: center; 
      font-weight: 600;
    }
    
    /* Form Elements */
    .form-group { 
      margin-bottom: 1.5rem; 
    }
    
    label { 
      font-weight: 600; 
      display: block; 
      margin-bottom: 0.5rem; 
      color: #0A2540;
    }
    
    input, select { 
      width: 100%; 
      padding: 1rem; 
      border: 1px solid #E9ECEF; 
      border-radius: 6px; 
      font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      font-size: 1rem;
    }
    
    input:focus, select:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
    }
    
    ::placeholder { 
      color: #adb5bd; 
      opacity: 1; 
    }
    
    button { 
      width: 100%; 
      background-color: #007bff; 
      color: white; 
      padding: 1rem 2.5rem; 
      border: none; 
      border-radius: 6px; 
      font-weight: 600; 
      cursor: pointer; 
      font-size: 1rem; 
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    
    button:hover { 
      background-color: #0056b3; 
      transform: translateY(-2px);
    }
    
    /* Messages */
    .message { 
      margin-bottom: 1.5rem; 
      padding: 1rem; 
      border-radius: 6px; 
      text-align: center; 
      font-weight: 500; 
    }
    
    .error-message { 
      background-color: #f8d7da; 
      color: #721c24; 
      border: 1px solid #f5c6cb; 
    }
    
    .success-message { 
      background-color: #d4edda; 
      color: #155724; 
      border: 1px solid #c3e6cb; 
    }
    
    /* Footer */
    footer { 
      background-color: #0A2540; 
      text-align: center; 
      padding: 1.5rem; 
      font-size: 0.9rem; 
      color: #ADB5BD;
    }
  </style>
</head>
<body>
  <header>
    <h1>Create your FinSight Account</h1>
    <nav>
      <a href="index.html">Home</a>
      <a href="login.php">Login</a>
    </nav>
  </header>

  <main>
    <div class="register-container">
      <h2>Register</h2>

      <?php if ($error_message): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>

      <?php if ($success_message): ?>
  <div class="message success-message">
    <?php echo htmlspecialchars($success_message); ?>
  </div>
  <script>
    // after 3 seconds, send them to login.php
    setTimeout(function(){
      window.location.href = 'login.php';
    }, 3000);
  </script>
      <?php else: ?>
      <form method="POST" action="register.php" novalidate>
        <div class="form-group">
          <label for="first-name">First Name:</label>
          <input type="text" id="first-name" name="first_name" placeholder="e.g., John" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="last-name">Last Name:</label>
          <input type="text" id="last-name" name="last_name" placeholder="e.g., Doe" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="email">Email:</label>
          <input type="email" id="email" name="email" placeholder="e.g., john.doe@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label for="password">Password (min 8 characters, at least one uppercase):</label>
          <input type="password" id="password" name="password" placeholder="Create a strong password" required>
        </div>

        <div class="form-group">
          <label for="confirm-password">Confirm Password:</label>
          <input type="password" id="confirm-password" name="confirm_password" placeholder="Enter your password again" required>
        </div>

        <div class="form-group">
          <label for="gender">Gender:</label>
          <select id="gender" name="gender" required>
            <option value="" disabled selected>Select your gender</option>
            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>

        <button type="submit">Register</button>
      </form>
      <?php endif; ?>
    </div>
  </main>

  <footer>
    <div class="footer-copyright">
      © 2025 FinSight Technologies. All rights reserved.
    </div>
  </footer>
</body>
</html>
