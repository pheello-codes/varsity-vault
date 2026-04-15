<?php
header('Content-Type: application/json');
include 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$cart_count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$cart_count_stmt->bind_param("i", $_SESSION['user_id']);
$cart_count_stmt->execute();
$cart_count_result = $cart_count_stmt->get_result()->fetch_assoc();

echo json_encode(['count' => (int)($cart_count_result['total_items'] ?? 0)]);
?>