<?php

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "wynn_fyp");
if ($conn->connect_error) die("Database connection failed");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    
    $sql = "SELECT User_ID, User_Login_Name, Password FROM user_file WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Database error");
    
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) die("Database error");
    
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id, $user_login_name, $hashed_password);
        $stmt->fetch();        
        
        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $user_id;
            $_SESSION["logged_in"] = true;
            $_SESSION["email"] = $email;
            $_SESSION["user_login_name"] = $user_login_name; 
            
            header("Location: dashboard.php?user_id=" . $user_id);
            exit();
        } else {
            $error_message = "Invalid password";
        }
    } else {
        $error_message = "User not found";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FinSight – Login</title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* Global Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Full-page layout */
    body {
        font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: #f8f9fa;
        color: #333;
        line-height: 1.7;
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
        text-align: center;
        line-height: 1.2;
        font-size: 1.8rem;
        font-weight: 600;
        letter-spacing: 0.5px;
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
        padding: 0.4rem 1rem;
        border-radius: 6px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        position: relative;
        overflow: hidden;
    }

    nav a::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: #fff;
        transform: scaleX(0);
        transform-origin: right;
        transition: transform 0.3s ease;
    }

    nav a:hover::before {
        transform: scaleX(1);
        transform-origin: left;
    }

    nav a:hover {
        color: #fff;
        background-color: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    /* Centered Main Content */
    main {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 3rem 1.5rem;
        position: relative;
    }

    /* Decorative Elements */
    main::before, main::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        z-index: -1;
        filter: blur(80px);
        opacity: 0.1;
    }

    main::before {
        background-color: #007bff;
        top: 10%;
        left: 5%;
    }

    main::after {
        background-color: #0A2540;
        bottom: 10%;
        right: 5%;
    }

    .login-container {
        background: #fff;
        padding: 3rem;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 420px;
        border: 1px solid #E9ECEF;
        position: relative;
        overflow: hidden;
        transform: translateY(0);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .login-container:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .login-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, #007bff, #00c6ff);
    }

    h2 {
        margin-bottom: 1.5rem;
        color: #0A2540;
        text-align: center;
        font-weight: 600;
        position: relative;
        padding-bottom: 1rem;
    }

    h2::after {
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
    .form-group {
        margin-bottom: 1.75rem;
        position: relative;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.75rem;
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
        padding-left: 2.75rem;
    }

    .input-with-icon input:focus + i {
        color: #007bff;
    }

    input {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 1px solid #E9ECEF;
        border-radius: 8px;
        font-size: 1rem;
        font-family: 'Inter', "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }

    input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
        background-color: #fff;
    }

    /* Button */
    button {
        width: 100%;
        padding: 1rem 2.5rem;
        border: none;
        background: linear-gradient(90deg, #007bff, #0056b3);
        color: white;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease;
    }

    button:hover::before {
        left: 100%;
    }

    button:hover {
        background: linear-gradient(90deg, #0062cc, #004494);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
    }

    button i {
        margin-right: 0.5rem;
    }

    /* Forgot Password */
    .forgot-password {
        margin-top: 1.75rem;
        text-align: center;
        position: relative;
        padding-top: 1.5rem;
    }

    .forgot-password::before {
        content: '';
        position: absolute;
        top: 0;
        left: 25%;
        width: 50%;
        height: 1px;
        background: linear-gradient(90deg, transparent, #E9ECEF, transparent);
    }

    a {
        color: #007bff;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-block;
    }

    a:hover {
        color: #0056b3;
        transform: translateY(-1px);
    }

    /* Footer */
    footer {
        background: linear-gradient(120deg, #0A2540 0%, #103155 100%);
        color: #ADB5BD;
        text-align: center;
        padding: 1.5rem;
        font-size: 0.9rem;
        position: relative;
        box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.05);
    }

    .footer-copyright {
        position: relative;
        z-index: 2;
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

    /* Responsive Adjustments */
    @media (max-width: 576px) {
        .login-container {
            padding: 2rem;
        }
        
        header h1 {
            font-size: 1.5rem;
        }
        
        main {
            padding: 2rem 1rem;
        }
    }
  </style>
</head>
<body>
  <header>
    <h1>Login to your FinSight account</h1>
    <nav>
      <a href="index.html"><i class="fas fa-home"></i> Home</a>
      <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
    </nav>
  </header>

  <main>
    <div class="login-container">
      <h2>Login</h2>
      <?php if (isset($error_message)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>
      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="email">Email:</label>
          <div class="input-with-icon">
            <input 
              type="email" 
              id="email" 
              name="email" 
              placeholder="Enter your email" 
              required
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
            >
            <i class="fas fa-envelope"></i>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password:</label>
          <div class="input-with-icon">
            <input 
              type="password" 
              id="password" 
              name="password" 
              placeholder="Enter your password" 
              required
            >
            <i class="fas fa-lock"></i>
          </div>
        </div>

        <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
      </form>
      
      <div class="forgot-password">
        <a href="forgot_password.php">Forgot Password?</a>
      </div>
    </div>
  </main>

  <footer>
    <div class="footer-copyright">
      © 2025 FinSight Technologies. All rights reserved.
    </div>
  </footer>
</body>
</html>