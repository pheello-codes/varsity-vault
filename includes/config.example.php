<?php
// Database configuration
// Copy this file to config.php and fill in your actual database credentials

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_name('varsity_vault_session');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}
session_start();

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'your_database_name';
$username = getenv('DB_USERNAME') ?: 'your_db_username';
$password = getenv('DB_PASSWORD') ?: 'your_db_password';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$paystack_secret_key = getenv('PAYSTACK_SECRET_KEY') ?: '';
$paystack_public_key = getenv('PAYSTACK_PUBLIC_KEY') ?: '';
define('PAYSTACK_SECRET_KEY', $paystack_secret_key);
define('PAYSTACK_PUBLIC_KEY', $paystack_public_key);
define('PLATFORM_COMMISSION_RATE', 0.23);

define('SITE_NAME', getenv('SITE_NAME') ?: 'Varsity Vault');
define('SITE_URL', getenv('SITE_URL') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/varsity-vault'));

define('EMAIL_FROM_ADDRESS', getenv('EMAIL_FROM_ADDRESS') ?: 'no-reply@varsityvault.com');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'Varsity Vault');
define('EMAIL_USE_SMTP', getenv('EMAIL_USE_SMTP') === '1');
define('EMAIL_SMTP_HOST', getenv('EMAIL_SMTP_HOST') ?: 'localhost');
define('EMAIL_SMTP_PORT', getenv('EMAIL_SMTP_PORT') ?: 25);

require_once __DIR__ . '/email.php';
?>