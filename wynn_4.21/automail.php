<?php
// automail.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer is installed and autoloaded via Composer
require 'vendor/autoload.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    // Set the debug level (4 is the most verbose)
    $mail->SMTPDebug = 0;
    
    // Set a custom debug output callback:
    $mail->Debugoutput = function($str, $level) {
        // Log debug info to a file (smtp_debug.log) in the same directory
        $logEntry = date('Y-m-d H:i:s') . " [Level $level] $str" . PHP_EOL;
        file_put_contents(__DIR__ . '/smtp_debug.log', $logEntry, FILE_APPEND);
        
        // Also echo debug information to the browser for immediate visibility
        echo "Debug [Level $level]: $str<br>";
    };

    try {
        // Server settings
        $mail->isSMTP();                                    // Use SMTP
        $mail->Host       = 'smtp.gmail.com';               // Gmail SMTP server
        $mail->SMTPAuth   = true;                           // Enable SMTP authentication
        $mail->Username   = 'vwap.imba123@gmail.com';       // Your Gmail address
        $mail->Password   = 'anze imna yqqg plix';           // Your Gmail password or App Password
        
        // Correct the encryption setting:
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;                       // TCP port for SSL

        // Recipients
        $mail->setFrom('vwap.imba123@gmail.com', 'FinSight');
        $mail->addAddress($to);  // Add recipient

        // Content
        $mail->isHTML(false);  // Set email format to plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        echo "Mailer Exception: " . $e->getMessage();
        return false;
    }
}
?>
