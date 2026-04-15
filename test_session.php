<?php
// Quick session test after logout
session_start();
echo json_encode([
    'session_exists' => isset($_SESSION['user_id']),
    'session_id' => session_id(),
    'session_data' => $_SESSION
]);
?>