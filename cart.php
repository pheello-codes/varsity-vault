<?php
$page_title = "Shopping Cart";
include 'includes/config.php';
include 'includes/auth_check.php';

$cartTableExists = tableExists('cart_items');
$cartErrors = [];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!$cartTableExists) {
        $cartErrors[] = 'Shopping cart is temporarily unavailable. Please try again later.';
    } else {
        if ($_POST['action'] == 'add_to_cart') {
            $note_id = (int)$_POST['note_id'];
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));

            // Check if note exists and is approved
            $note_check = $conn->prepare("SELECT id FROM notes WHERE id = ? AND status = 'approved'");
            $note_check->bind_param("i", $note_id);
            $note_check->execute();

            if ($note_check->get_result()->num_rows > 0) {
                // Insert or update cart item
                $cart_stmt = $conn->prepare("INSERT INTO cart_items (user_id, note_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                if ($cart_stmt) {
                    $cart_stmt->bind_param("iiii", $_SESSION['user_id'], $note_id, $quantity, $quantity);
                    $cart_stmt->execute();
                }

                header("Location: cart.php");
                exit();
            }
        } elseif ($_POST['action'] == 'remove_from_cart') {
            $note_id = (int)$_POST['note_id'];

            $delete_stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND note_id = ?");
            if ($delete_stmt) {
                $delete_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
                $delete_stmt->execute();
            }

            header("Location: cart.php");
            exit();
        } elseif ($_POST['action'] == 'update_quantity') {
            $note_id = (int)$_POST['note_id'];
            $quantity = max(1, (int)$_POST['quantity']);

            if ($quantity > 0) {
                $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND note_id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("iii", $quantity, $_SESSION['user_id'], $note_id);
                    $update_stmt->execute();
                }
            } else {
                $delete_stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND note_id = ?");
                if ($delete_stmt) {
                    $delete_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
                    $delete_stmt->execute();
                }
            }

            header("Location: cart.php");
            exit();
        }
    }
}

$cart_items = [];
if ($cartTableExists) {
    $cart_stmt = $conn->prepare("SELECT ci.*, n.title, n.price, n.module_code, n.university FROM cart_items ci JOIN notes n ON ci.note_id = n.id WHERE ci.user_id = ? AND n.status = 'approved'");
    if ($cart_stmt) {
        $cart_stmt->bind_param("i", $_SESSION['user_id']);
        $cart_stmt->execute();
        $cart_items = $cart_stmt->get_result();
    }
}

// Calculate total
$total = 0;
$valid_items = [];
if ($cartTableExists && $cart_items) {
    while ($item = $cart_items->fetch_assoc()) {
        $item_total = $item['price'] * $item['quantity'];
        $total += $item_total;
        $valid_items[] = $item;
    }
} else {
    $valid_items = [];
}

?>

<?php include 'includes/header.php'; ?>

<?php if (!empty($cartErrors)): ?>
    <div class="max-w-4xl mx-auto mb-6">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc list-inside">
                <?php foreach ($cartErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="max-w-4xl mx-auto pb-32">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

    <?php if (empty($valid_items)): ?>
        <div class="text-center py-12">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.1 5H19M7 13l-1.1 5M7 13h10m0 0v8a2 2 0 01-2 2H9a2 2 0 01-2-2v-8z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h3>
            <p class="text-gray-500 mb-6">Add some notes to get started!</p>
            <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">Browse Notes</a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Cart Items</h2>
            </div>
            <div class="divide-y divide-gray-200">
                <?php foreach ($valid_items as $item): ?>
                    <?php $item_total = $item['price'] * $item['quantity']; ?>
                    <div class="p-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p class="text-gray-600"><?php echo htmlspecialchars($item['module_code']); ?> - <?php echo htmlspecialchars($item['university']); ?></p>
                            <p class="text-gray-600">Price: R<?php echo number_format($item['price'], 2); ?></p>
                        </div>
                        <div class="flex flex-col gap-3 items-start sm:items-end">
                            <div class="flex items-center gap-2">
                                <form method="POST" class="inline-flex">
                                    <input type="hidden" name="action" value="update_quantity">
                                    <input type="hidden" name="note_id" value="<?php echo $item['note_id']; ?>">
                                    <input type="hidden" name="quantity" value="<?php echo max(1, $item['quantity'] - 1); ?>">
                                    <button type="submit" class="inline-flex items-center justify-center w-11 h-11 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">-</button>
                                </form>
                                <span class="min-w-[44px] text-center text-lg font-semibold"><?php echo $item['quantity']; ?></span>
                                <form method="POST" class="inline-flex">
                                    <input type="hidden" name="action" value="update_quantity">
                                    <input type="hidden" name="note_id" value="<?php echo $item['note_id']; ?>">
                                    <input type="hidden" name="quantity" value="<?php echo $item['quantity'] + 1; ?>">
                                    <button type="submit" class="inline-flex items-center justify-center w-11 h-11 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">+</button>
                                </form>
                            </div>
                            <span class="text-xl font-bold text-blue-600">R<?php echo number_format($item_total, 2); ?></span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="remove_from_cart">
                                <input type="hidden" name="note_id" value="<?php echo $item['note_id']; ?>">
                                <button type="submit" class="w-full sm:w-auto bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-lg transition duration-300">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 sticky bottom-0 left-0 right-0 z-10">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between text-2xl font-bold">
                    <span>Total:</span>
                    <span class="text-blue-600">R<?php echo number_format($total, 2); ?></span>
                </div>
                <form method="POST" action="checkout.php">
                    <button type="submit" class="w-full bg-green-600 text-white px-8 py-4 rounded-lg hover:bg-green-700 transition duration-300 text-lg font-semibold">
                        Proceed to Checkout
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>