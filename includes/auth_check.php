<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Optional: Verify user still exists in database
if (isset($conn)) {
    try {
        $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        if ($user_check) {
            $user_check->bind_param("i", $_SESSION['user_id']);
            $user_check->execute();
            $user_result = $user_check->get_result();
            if ($user_result->num_rows === 0) {
                // User no longer exists, destroy session
                session_destroy();
                header("Location: login.php?error=user_not_found");
                exit();
            }
        }
    } catch (Exception $e) {
        // Log error but don't block access
        error_log('Auth check error: ' . $e->getMessage());
    }
}
?>