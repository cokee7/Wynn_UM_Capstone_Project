<?php
// reset_password_config.php

$servername = "localhost";   
$username   = "root";        
$password   = "";            
$dbname     = "wynn_fyp";    

try {
    // Use $servername instead of $host in your DSN.
    $pdo = new PDO("mysql:host={$servername};dbname={$dbname}", $username, $password);
    // Set error mode to exception to catch errors properly
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database {$dbname}: " . $e->getMessage());
}
?>
