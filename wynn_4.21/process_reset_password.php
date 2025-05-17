<?php
// process_reset_password.php

ini_set('display_errors', 1);
error_reporting(E_ALL);


session_start();
require 'reset_password_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify that both password fields match
    if ($password !== $confirm_password) {
        echo "Passwords do not match. Please try again.";
        exit;
    }

    // Validate password: minimum 8 characters and at least one uppercase letter
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password)) {
        echo "Password must be at least 8 characters long and contain at least one uppercase letter.";
        exit;
    }

    // Verify the token again
    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRequest) {
        echo "Invalid or expired token.";
        exit;
    }

    $user_id = $resetRequest['user_id'];

    // Hash the new password securely
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Update the password in the user_file table
    $stmt = $pdo->prepare("UPDATE user_file SET password = ? WHERE User_ID = ?");
    $stmt->execute([$hashedPassword, $user_id]);

    // Invalidate the token so it cannot be used again (delete it)
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);

    echo "Your password has been successfully reset. You can now <a href='login.php'>login</a>.";
} else {
    header("Location: forgot_password.php");
    exit;
}
?>
