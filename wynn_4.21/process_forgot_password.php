<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/reset_password_config.php';
require_once __DIR__ . '/automail.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists in the user_file table
    $stmt = $pdo->prepare("SELECT User_ID FROM user_file WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Email address not found. Please try again.<br>";
        echo '<a href="forgot_password.php">Go back</a>';
        exit;
    }

    $user_id = $user['User_ID'];

    // Generate a secure token
    $token = bin2hex(random_bytes(16));

    // Set token expiration to 60 minutes after generation
    $expires_at = date("Y-m-d H:i:s", time() + 3600);

    // Insert token, user_id, and expires_at into password_resets table
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $token, $expires_at]);

    // Create the password reset link
    $reset_link = "http://localhost/wynn_4.20/reset_password.php?token=" . urlencode($token);

    // Prepare email content
    $subject = "Password Reset Request";
    $body = "Dear user,\n\nYou requested a password reset. Please click the link below to reset your password (this link will expire in 60 minutes):\n\n"
          . $reset_link . "\n\nIf you did not request a password reset, please ignore this email.\n\nBest regards,\nFinSight Support";

    // Send the email
    sendEmail($email, $subject, $body);

    // Output a message and redirect after 3 seconds
    echo '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Password Reset</title>
  <meta http-equiv="refresh" content="3;url=login.php">
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
  </style>
</head>
<body>
  <p>Please check your email. A reset link has been sent.</p>
  <p>You will be redirected shortly...</p>
</body>
</html>';
    exit;
} else {
    header("Location: forgot_password.php");
    exit;
}
?>
