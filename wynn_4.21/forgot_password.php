<!-- forgot_password.php -->
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FinSight – Forgot Password</title>
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

    .forgot-password-container {
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

    .forgot-password-container:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .forgot-password-container::before {
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

    .button-secondary {
        background: transparent;
        border: 1px solid #007bff;
        color: #007bff;
    }

    .button-secondary:hover {
        background: rgba(0, 123, 255, 0.1);
        color: #0056b3;
        border-color: #0056b3;
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

    /* Responsive Adjustments */
    @media (max-width: 576px) {
        .forgot-password-container {
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
    <h1>Reset Your FinSight Account Password</h1>
    <nav>
      <a href="index.html"><i class="fas fa-home"></i> Home</a>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
      <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
    </nav>
  </header>

  <main>
    <div class="forgot-password-container">
      <h2>Forgot Password</h2>
      <form action="process_forgot_password.php" method="post">
        <div class="form-group">
          <label for="email">Email Address:</label>
          <div class="input-with-icon">
            <input type="email" name="email" id="email" placeholder="Enter your email address" required />
            <i class="fas fa-envelope"></i>
          </div>
        </div>
        <button type="submit"><i class="fas fa-paper-plane"></i> Submit</button>
        <button type="button" class="button-secondary" onclick="window.history.back();"><i class="fas fa-arrow-left"></i> Back</button>
      </form>
    </div>
  </main>

  <footer>
    <div class="footer-copyright">
      © 2025 FinSight Technologies. All rights reserved.
    </div>
  </footer>
</body>
</html>
