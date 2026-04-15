<?php
// Start the session
session_name('varsity_vault_session');
session_start();

// Clear all session variables
$_SESSION = [];

// Delete the session cookie with EXACT parameters from config
if (ini_get('session.use_cookies')) {
    setcookie(
        'varsity_vault_session',  // Exact session name from config
        '',
        time() - 3600,
        '/varsity-vault',         // Exact path from config
        '',
        false,
        true
    );
}

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: /varsity-vault/index.php");
exit;
?>