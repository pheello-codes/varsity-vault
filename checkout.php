<?php
$page_title = "Checkout";
include 'includes/config.php';
include 'includes/auth_check.php';
include 'includes/paystack.php';

// Get cart items from localStorage (sent via POST)
$cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Calculate total and validate order items
$total = 0;
$valid_items = [];

foreach ($cart_items as $item) {
    $stmt = $conn->prepare("SELECT id, title, price FROM notes WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $note = $result->fetch_assoc();
        $quantity = max(1, (int) $item['quantity']);
        $note['quantity'] = $quantity;
        $valid_items[] = $note;
        $total += $note['price'] * $quantity;
    }
}

if (empty($valid_items)) {
    header("Location: index.php");
    exit();
}

$emailStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$emailStmt->bind_param("i", $_SESSION['user_id']);
$emailStmt->execute();
$userEmail = $emailStmt->get_result()->fetch_assoc()['email'] ?? '';

$paystackInit = null;
$paystackReference = '';

if (!empty($userEmail)) {
    $paystackInit = initializePayment($userEmail, $total, ['cart_items' => $cart_items]);
    if ($paystackInit['status']) {
        $paystackReference = $paystackInit['data']['reference'];
    } else {
        $error = $paystackInit['message'];
    }
} else {
    $error = 'Unable to read your email for payment initialization.';
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

    <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6">Order Summary</h2>
            <div class="space-y-4">
                <?php foreach ($valid_items as $item): ?>
                    <div class="flex justify-between items-center border-b pb-4">
                        <div>
                            <h3 class="font-semibold"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="text-gray-600">Quantity: <?php echo $item['quantity']; ?></p>
                        </div>
                        <span class="font-bold">R<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 pt-6 border-t">
                <div class="flex justify-between items-center text-xl font-bold">
                    <span>Total:</span>
                    <span>R<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6">Payment Details</h2>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-6">
                <strong>Note:</strong> Paystack will handle the payment securely. After payment, you will be returned here for verification.
            </div>

            <?php if (!empty($paystackReference) && empty($error)): ?>
                <button id="paystack-button" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition duration-300 w-full text-lg font-semibold">
                    Pay R<?php echo number_format($total, 2); ?> with Paystack
                </button>
            <?php else: ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    Unable to initialize Paystack payment. Please check your Paystack configuration.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($paystackReference) && empty($error)): ?>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        document.getElementById('paystack-button').addEventListener('click', function () {
            const handler = PaystackPop.setup({
                key: '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                email: '<?php echo htmlspecialchars($userEmail); ?>',
                amount: <?php echo (int) round($total * 100); ?>,
                currency: 'ZAR',
                ref: '<?php echo htmlspecialchars($paystackReference); ?>',
                onClose: function() {
                    alert('Payment window closed.');
                },
                callback: function(response) {
                    window.location.href = 'verify-payment.php?reference=' + response.reference;
                }
            });
            handler.openIframe();
        });
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>