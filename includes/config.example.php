<?php
// Database configuration
// Copy this file to config.php and fill in your actual database credentials

$host = 'localhost'; // Database host (usually 'localhost')
$dbname = 'your_database_name'; // Database name
$username = 'your_db_username'; // Database username
$password = 'your_db_password'; // Database password

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Paystack configuration
$paystack_secret_key = getenv('PAYSTACK_SECRET_KEY') ?: '';
$paystack_public_key = getenv('PAYSTACK_PUBLIC_KEY') ?: '';
define('PAYSTACK_SECRET_KEY', $paystack_secret_key);
define('PAYSTACK_PUBLIC_KEY', $paystack_public_key);
define('PLATFORM_COMMISSION_RATE', 0.23);

// Start session for all pages
session_start();
?>