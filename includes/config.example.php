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

// Start session for all pages
session_start();
?>