<?php
$page_title = "Payment Verification";
include 'includes/config.php';
include 'includes/auth_check.php';
include 'includes/paystack.php';

$error = '';
$success = false;

if (!isset($_GET['reference']) || empty($_GET['reference'])) {
    header('Location: cart.php');
    exit();
}

$reference = trim($_GET['reference']);
$verifyResult = verifyPayment($reference);

if (!$verifyResult['status']) {
    $error = $verifyResult['message'];
} else {
    $payment = $verifyResult['data'];

    if (empty($payment['status']) || $payment['status'] !== 'success') {
        $error = 'Payment verification failed or is not successful.';
    } else {
        // Get cart items from database instead of metadata
        $cart_stmt = $conn->prepare("
            SELECT ci.quantity, n.id, n.title, n.price, n.seller_id, u.email AS seller_email, u.name AS seller_name
            FROM cart_items ci
            JOIN notes n ON ci.note_id = n.id
            JOIN users u ON n.seller_id = u.id
            WHERE ci.user_id = ? AND n.status = 'approved'
        ");
        $cart_stmt->bind_param("i", $_SESSION['user_id']);
        $cart_stmt->execute();
        $cart_items_result = $cart_stmt->get_result();

        $cartItems = [];
        while ($item = $cart_items_result->fetch_assoc()) {
            $cartItems[] = $item;
        }

        if (empty($cartItems)) {
            $error = 'No cart items found for this user.';
        } else {
            $amountPaid = (isset($payment['amount']) ? (int) $payment['amount'] : 0);
            $totalExpected = 0;
            $validItems = [];

            foreach ($cartItems as $item) {
                $quantity = max(1, (int) $item['quantity']);
                $item['quantity'] = $quantity;
                $validItems[] = $item;
                $totalExpected += $item['price'] * $quantity;
                }
            }

            if ((int) round($totalExpected * 100) !== $amountPaid) {
                $error = 'Payment amount does not match the order total.';
            } else {
                $conn->begin_transaction();
                try {
                    $order_html_items = '';
                    $buyer_name = $_SESSION['user_name'] ?? 'Student';
                    $purchase_count = 0;

                    foreach ($validItems as $item) {
                        $order_html_items .= '<p style="margin:6px 0;">' . htmlspecialchars($item['title']) . ' - R' . number_format($item['price'], 2) . ' x ' . $item['quantity'] . '</p>';
                        $checkStmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND note_id = ?");
                        $checkStmt->bind_param('ii', $_SESSION['user_id'], $item['id']);
                        $checkStmt->execute();

                        if ($checkStmt->get_result()->num_rows === 0) {
                            $purchaseStmt = $conn->prepare("INSERT INTO purchases (user_id, note_id) VALUES (?, ?)");
                            $purchaseStmt->bind_param('ii', $_SESSION['user_id'], $item['id']);
                            $purchaseStmt->execute();
                            $purchaseId = $conn->insert_id;

                            $totalAmount = $item['price'] * $item['quantity'];
                            $commissionAmount = round($totalAmount * PLATFORM_COMMISSION_RATE, 2);
                            $sellerAmount = round($totalAmount - $commissionAmount, 2);

                            $earningStmt = $conn->prepare("INSERT INTO seller_earnings (seller_id, purchase_id, note_id, total_amount, commission_amount, seller_amount) VALUES (?, ?, ?, ?, ?, ?)");
                            $earningStmt->bind_param('iiiddd', $item['seller_id'], $purchaseId, $item['id'], $totalAmount, $commissionAmount, $sellerAmount);
                            $earningStmt->execute();

                            send_template_email('sale_notification', $item['seller_email'], [
                                'seller_name' => $item['seller_name'],
                                'note_title' => $item['title'],
                                'amount' => $totalAmount,
                                'buyer_name' => $buyer_name,
                            ]);

                            $purchase_count++;
                        }
                    }

                    if ($purchase_count > 0) {
                        send_template_email('purchase_confirmation', $userEmail, [
                            'name' => $buyer_name,
                            'total' => $totalExpected,
                            'items_html' => $order_html_items,
                        ]);
                    }

                    $conn->commit();
                    
                    // Clear the user's cart after successful purchase
                    $clear_cart_stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
                    $clear_cart_stmt->bind_param("i", $_SESSION['user_id']);
                    $clear_cart_stmt->execute();
                    
                    $success = true;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Could not record purchase after payment verification.';
                }
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">Payment Verification</h1>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="cart.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">Return to Cart</a>
    <?php else: ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Payment confirmed!</strong> Your order is complete and your purchases have been recorded.
        </div>
        <p class="text-gray-700 mb-6">You can now access your purchased notes in your dashboard.</p>
        <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">Go to Dashboard</a>
        <script>
            localStorage.removeItem('cart');
        </script>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>