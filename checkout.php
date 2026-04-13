<?php
$page_title = "Checkout";
include 'includes/config.php';
include 'includes/auth_check.php';

// Get cart items from localStorage (sent via POST)
$cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];

if (empty($cart_items)) {
    header("Location: index.php");
    exit();
}

// Calculate total
$total = 0;
$valid_items = [];

foreach ($cart_items as $item) {
    $stmt = $conn->prepare("SELECT id, title, price FROM notes WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $note = $result->fetch_assoc();
        $note['quantity'] = $item['quantity'];
        $valid_items[] = $note;
        $total += $note['price'] * $item['quantity'];
    }
}

if (empty($valid_items)) {
    header("Location: index.php");
    exit();
}

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_purchase'])) {
    $conn->begin_transaction();

    try {
        foreach ($valid_items as $item) {
            // Check if already purchased
            $check_stmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND note_id = ?");
            $check_stmt->bind_param("ii", $_SESSION['user_id'], $item['id']);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows == 0) {
                // Insert purchase
                $purchase_stmt = $conn->prepare("INSERT INTO purchases (user_id, note_id) VALUES (?, ?)");
                $purchase_stmt->bind_param("ii", $_SESSION['user_id'], $item['id']);
                $purchase_stmt->execute();
            }
        }

        $conn->commit();

        // Clear cart (in JavaScript, but we'll redirect)
        echo "<script>localStorage.removeItem('cart');</script>";

        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Purchase failed. Please try again.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php if (isset($success)): ?>
    <div class="max-w-2xl mx-auto">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Success!</strong> Your purchase has been completed. You can now access your notes in your dashboard.
        </div>
        <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">Go to Dashboard</a>
    </div>
<?php else: ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6">Order Summary</h2>

            <div class="space-y-4">
                <?php foreach ($valid_items as $item): ?>
                    <div class="flex justify-between items-center border-b pb-4">
                        <div>
                            <h3 class="font-semibold"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="text-gray-600">Quantity: <?php echo $item['quantity']; ?></p>
                        </div>
                        <span class="font-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 pt-6 border-t">
                <div class="flex justify-between items-center text-xl font-bold">
                    <span>Total:</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6">Payment Details</h2>

            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                <strong>Note:</strong> This is a demo application. In a real system, you would integrate with a payment processor like Stripe or PayPal.
            </div>

            <form method="POST">
                <input type="hidden" name="cart_items" value='<?php echo json_encode($cart_items); ?>'>
                <input type="hidden" name="confirm_purchase" value="1">

                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Card Number</label>
                    <input type="text" placeholder="1234 5678 9012 3456" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Expiry Date</label>
                        <input type="text" placeholder="MM/YY" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">CVV</label>
                        <input type="text" placeholder="123" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" disabled>
                    </div>
                </div>

                <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition duration-300 w-full text-lg font-semibold">
                    Complete Purchase
                </button>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>