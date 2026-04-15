<?php
$page_title = "Dashboard";
include 'includes/config.php';
include 'includes/auth_check.php';
include 'includes/paystack.php';

$errors = [];
$messages = [];

// Handle note deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $note_id = (int)$_POST['note_id'];

    $verify_stmt = $conn->prepare("SELECT seller_id FROM notes WHERE id = ?");
    $verify_stmt->bind_param("i", $note_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    $note = $result->fetch_assoc();

    if ($note && $note['seller_id'] == $_SESSION['user_id']) {
        $delete_stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $delete_stmt->bind_param("i", $note_id);
        $delete_stmt->execute();

        $conn->query("DELETE FROM purchases WHERE note_id = $note_id");
        $conn->query("DELETE FROM reviews WHERE note_id = $note_id");

        $messages[] = "Note deleted successfully.";
    }
}

$user_detail_stmt = $conn->prepare("SELECT bank_code, account_number, account_name, recipient_code FROM users WHERE id = ?");
$user_detail_stmt->bind_param("i", $_SESSION['user_id']);
$user_detail_stmt->execute();
$bank_info = $user_detail_stmt->get_result()->fetch_assoc();

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'request_withdrawal') {
    $earnings_stmt = $conn->prepare("SELECT COALESCE(SUM(seller_amount), 0) AS total_earned FROM seller_earnings WHERE seller_id = ?");
    $earnings_stmt->bind_param("i", $_SESSION['user_id']);
    $earnings_stmt->execute();
    $total_earned = $earnings_stmt->get_result()->fetch_assoc()['total_earned'];

    $withdrawn_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_withdrawn FROM withdrawals WHERE user_id = ? AND status IN ('processing', 'completed', 'pending_funds')");
    $withdrawn_stmt->bind_param("i", $_SESSION['user_id']);
    $withdrawn_stmt->execute();
    $total_withdrawn = $withdrawn_stmt->get_result()->fetch_assoc()['total_withdrawn'];

    $available_balance = max(0, $total_earned - $total_withdrawn);
    $withdrawalAmount = round($available_balance, 2);

    if (empty($bank_info['recipient_code']) || empty($bank_info['bank_code']) || empty($bank_info['account_number'])) {
        $errors[] = 'Please add your bank details in your profile before requesting a withdrawal.';
    } elseif ($withdrawalAmount < 100) {
        $errors[] = 'You need at least R100 available balance to request a withdrawal.';
    } else {
        $balanceResult = getPaystackBalance();

        if (!$balanceResult['status']) {
            $errors[] = 'Unable to check Paystack balance: ' . htmlspecialchars($balanceResult['message']);
        } else {
            $paystackBalance = 0;
            if (!empty($balanceResult['data']['available'][0]['amount'])) {
                $paystackBalance = (int) $balanceResult['data']['available'][0]['amount'] / 100;
            }

            if ($paystackBalance < $withdrawalAmount) {
                $insert_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status) VALUES (?, ?, 'pending_funds')");
                $insert_stmt->bind_param("id", $_SESSION['user_id'], $withdrawalAmount);
                $insert_stmt->execute();

                $messages[] = 'Your withdrawal request is pending because the Paystack balance is insufficient. Admin will need to top up.';
            } else {
                $transferResult = initiateTransfer($bank_info['recipient_code'], $withdrawalAmount, 'Seller payout');
                $status = 'processing';
                $transfer_code = null;
                $paystack_reference = null;
                $processed_at = null;

                if ($transferResult['status']) {
                    $transferData = $transferResult['data'];
                    $transfer_code = $transferData['transfer_code'] ?? null;
                    $paystack_reference = $transferData['reference'] ?? null;

                    if (!empty($transferData['status']) && $transferData['status'] === 'success') {
                        $status = 'completed';
                        $processed_at = date('Y-m-d H:i:s');
                    }

                    $insert_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status, transfer_code, paystack_reference, processed_at) VALUES (?, ?, ?, ?, ?, ?)");
                    $insert_stmt->bind_param("idssss", $_SESSION['user_id'], $withdrawalAmount, $status, $transfer_code, $paystack_reference, $processed_at);
                    $insert_stmt->execute();

                    $messages[] = 'Withdrawal request created. Transfer status: ' . ucfirst(str_replace('_', ' ', $status)) . '.';
                } else {
                    $insert_stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, status, paystack_reference) VALUES (?, ?, 'failed', ?)");
                    $insert_stmt->bind_param("ids", $_SESSION['user_id'], $withdrawalAmount, $paystack_reference);
                    $insert_stmt->execute();

                    $errors[] = 'Paystack transfer failed: ' . htmlspecialchars($transferResult['message']);
                }
            }
        }
    }
}

// Get user's purchases
$purchase_stmt = $conn->prepare("SELECT p.purchase_date, n.id AS note_id, n.title, n.module_code, n.university, n.file_path FROM purchases p JOIN notes n ON p.note_id = n.id WHERE p.user_id = ? ORDER BY p.purchase_date DESC");
$purchase_stmt->bind_param("i", $_SESSION['user_id']);
$purchase_stmt->execute();
$purchases = $purchase_stmt->get_result();

$earnings_stmt = $conn->prepare("SELECT COALESCE(SUM(seller_amount), 0) AS total_earned FROM seller_earnings WHERE seller_id = ?");
$earnings_stmt->bind_param("i", $_SESSION['user_id']);
$earnings_stmt->execute();
$earningsRow = $earnings_stmt->get_result()->fetch_assoc();
$total_earned = $earningsRow['total_earned'] ?? 0;

$withdrawn_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_withdrawn FROM withdrawals WHERE user_id = ? AND status IN ('processing', 'completed', 'pending_funds')");
$withdrawn_stmt->bind_param("i", $_SESSION['user_id']);
$withdrawn_stmt->execute();
$withdrawnRow = $withdrawn_stmt->get_result()->fetch_assoc();
$total_withdrawn = $withdrawnRow['total_withdrawn'] ?? 0;

$available_balance = max(0, $total_earned - $total_withdrawn);

$withdrawal_history_stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC");
$withdrawal_history_stmt->bind_param("i", $_SESSION['user_id']);
$withdrawal_history_stmt->execute();
$withdrawals = $withdrawal_history_stmt->get_result();

// Get user's listings
$listing_stmt = $conn->prepare("SELECT * FROM notes WHERE seller_id = ? ORDER BY created_at DESC");
$listing_stmt->bind_param("i", $_SESSION['user_id']);
$listing_stmt->execute();
$listings = $listing_stmt->get_result();
?>

<?php include 'includes/header.php'; ?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600 mt-2">Manage your purchases, listings, and seller payouts.</p>
</div>

<?php foreach ($messages as $message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endforeach; ?>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-4">Seller Wallet</h2>
        <p class="text-gray-600 mb-6">Available balance is calculated from your approved note sales minus your requested withdrawals.</p>

        <div class="grid grid-cols-1 gap-4">
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-500">Total earned</p>
                <p class="text-3xl font-bold text-green-600">R<?php echo number_format($total_earned, 2); ?></p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-500">Reserved for withdrawals</p>
                <p class="text-3xl font-bold text-yellow-600">R<?php echo number_format($total_withdrawn, 2); ?></p>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-500">Available balance</p>
                <p class="text-3xl font-bold text-blue-600">R<?php echo number_format($available_balance, 2); ?></p>
            </div>
        </div>

        <div class="mt-6">
            <?php if (empty($bank_info['recipient_code'])): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded mb-4">
                    Add your bank details in <a href="profile.php" class="underline text-blue-600">Profile</a> before requesting payouts.
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="request_withdrawal">
                <button type="submit" class="w-full <?php echo (empty($bank_info['recipient_code']) || $available_balance < 100) ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-6 py-3 rounded-lg transition duration-300" <?php echo (empty($bank_info['recipient_code']) || $available_balance < 100) ? 'disabled' : ''; ?>>
                    Request Withdrawal
                </button>
            </form>

            <p class="text-sm text-gray-500 mt-3">Withdrawals require a minimum of R100 and valid Paystack bank recipient details.</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-4">Withdrawal History</h2>

        <?php if ($withdrawals->num_rows > 0): ?>
            <div class="space-y-3">
                <?php while ($withdrawal = $withdrawals->fetch_assoc()): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <p class="text-lg font-semibold">R<?php echo number_format($withdrawal['amount'], 2); ?></p>
                                <p class="text-sm text-gray-500">Requested <?php echo date('M j, Y', strtotime($withdrawal['requested_at'])); ?></p>
                            </div>
                            <span class="text-sm font-semibold <?php echo $withdrawal['status'] == 'completed' ? 'text-green-600' : ($withdrawal['status'] == 'failed' ? 'text-red-600' : 'text-yellow-600'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $withdrawal['status'])); ?>
                            </span>
                        </div>
                        <div class="mt-3 text-sm text-gray-600">
                            Transfer Code: <?php echo htmlspecialchars($withdrawal['transfer_code'] ?? '-'); ?><br>
                            Paystack Ref: <?php echo htmlspecialchars($withdrawal['paystack_reference'] ?? '-'); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No withdrawals yet.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-6">My Library</h2>

        <?php if ($purchases->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($purchase = $purchases->fetch_assoc()): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($purchase['title']); ?></h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($purchase['module_code']); ?> - <?php echo htmlspecialchars($purchase['university']); ?></p>
                        <p class="text-sm text-gray-500">Purchased on <?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></p>
                        <?php if ($purchase['file_path']): ?>
                            <a href="download.php?note_id=<?php echo $purchase['note_id']; ?>" target="_blank" class="inline-block mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-300">
                                Download PDF
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">You haven't purchased any notes yet.</p>
            <a href="index.php" class="inline-block mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-300">Browse Notes</a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-6">My Listings</h2>

        <?php if ($listings->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($listing = $listings->fetch_assoc()): ?>
                    <?php
                    // Get rating for this note
                    $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE note_id = ?");
                    $rating_stmt->bind_param("i", $listing['id']);
                    $rating_stmt->execute();
                    $rating = $rating_stmt->get_result()->fetch_assoc();
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($listing['title']); ?></h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($listing['module_code']); ?> - <?php echo htmlspecialchars($listing['university']); ?></p>
                        <p class="text-sm text-gray-500">R<?php echo number_format($listing['price'], 2); ?> - Status: 
                            <span class="font-semibold <?php echo $listing['status'] == 'approved' ? 'text-green-600' : ($listing['status'] == 'pending' ? 'text-yellow-600' : 'text-red-600'); ?>">
                                <?php echo ucfirst($listing['status']); ?>
                            </span>
                        </p>
                        <?php if ($rating['review_count'] > 0): ?>
                            <div class="flex items-center mt-2">
                                <div class="flex items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="w-4 h-4 <?php echo $i <= round($rating['avg_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="ml-2 text-sm text-gray-600">(<?php echo number_format($rating['avg_rating'], 1); ?> from <?php echo $rating['review_count']; ?> review<?php echo $rating['review_count'] > 1 ? 's' : ''; ?>)</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($listing['status'] == 'approved'): ?>
                            <p class="text-xs text-gray-400">Listed on <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex gap-2">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this note? This action cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="note_id" value="<?php echo $listing['id']; ?>">
                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 transition duration-300">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500">You haven't listed any notes yet.</p>
        <?php endif; ?>

        <a href="upload.php" class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-300">Upload New Notes</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>