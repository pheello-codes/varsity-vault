<?php
$page_title = "Paystack Dashboard";
include '../includes/config.php';
include '../includes/paystack.php';

// Check admin access
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$userStmt = $conn->prepare('SELECT is_admin FROM users WHERE id = ?');
$userStmt->bind_param('i', $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user || !$user['is_admin']) {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="eft-payments-' . date('Y-m-d') . '.csv"');

    $withdrawalsStmt = $conn->prepare("SELECT w.id, u.name as user_name, u.email as user_email, w.amount, w.status, w.transfer_code, w.paystack_reference, w.requested_at, w.processed_at FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.requested_at DESC");
    $withdrawalsStmt->execute();
    $withdrawals = $withdrawalsStmt->get_result();

    echo "ID,User,Email,Amount,Status,Transfer Code,Paystack Reference,Requested At,Processed At\n";
    while ($row = $withdrawals->fetch_assoc()) {
        echo implode(',', [
            $row['id'], 
            '"' . str_replace('"', '""', $row['user_name']) . '"',
            '"' . str_replace('"', '""', $row['user_email']) . '"',
            number_format($row['amount'], 2),
            $row['status'],
            $row['transfer_code'],
            $row['paystack_reference'],
            $row['requested_at'],
            $row['processed_at'] ?? ''
        ]) . "\n";
    }
    exit();
}

$balanceResult = getPaystackBalance();
$balanceData = [];

if ($balanceResult['status']) {
    $balanceData = $balanceResult['data'];
} else {
    $error = $balanceResult['message'];
}

$withdrawalStatsStmt = $conn->prepare("SELECT status, COUNT(*) as count, SUM(amount) as total FROM withdrawals GROUP BY status");
$withdrawalStatsStmt->execute();
$withdrawalStats = $withdrawalStatsStmt->get_result();

$withdrawalsStmt = $conn->prepare("SELECT w.*, u.name as seller_name, u.email as seller_email FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.requested_at DESC LIMIT 50");
$withdrawalsStmt->execute();
$withdrawals = $withdrawalsStmt->get_result();

$statusSummary = [
    'processing' => ['count' => 0, 'total' => 0],
    'completed' => ['count' => 0, 'total' => 0],
    'pending_funds' => ['count' => 0, 'total' => 0],
    'failed' => ['count' => 0, 'total' => 0]
];

while ($row = $withdrawalStats->fetch_assoc()) {
    $statusSummary[$row['status']] = [
        'count' => (int) $row['count'],
        'total' => (float) $row['total']
    ];
}

$paystackBalance = 0;
if (!empty($balanceData['available']) && is_array($balanceData['available'])) {
    $paystackBalance = $balanceData['available'][0]['amount'] / 100;
}
?>

<?php include '../includes/header.php'; ?>

<div class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Paystack Dashboard</h1>
        <p class="text-gray-600 mt-2">Monitor transfers, balance, and seller payouts.</p>
    </div>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <a href="../admin/index.php" class="w-full sm:w-auto bg-gray-200 text-gray-800 px-4 py-3 rounded hover:bg-gray-300 text-center">Back to Admin</a>
        <a href="?export_csv=1" class="w-full sm:w-auto bg-blue-600 text-white px-4 py-3 rounded hover:bg-blue-700 text-center">Export EFT CSV</a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900">Available Paystack Balance</h3>
        <p class="text-3xl font-bold text-green-600 mt-4">R<?php echo number_format($paystackBalance, 2); ?></p>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900">Pending Funds</h3>
        <p class="text-3xl font-bold text-yellow-600 mt-4"><?php echo $statusSummary['pending_funds']['count']; ?> requests</p>
        <p class="text-gray-500">R<?php echo number_format($statusSummary['pending_funds']['total'], 2); ?></p>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900">Failed Transfers</h3>
        <p class="text-3xl font-bold text-red-600 mt-4"><?php echo $statusSummary['failed']['count']; ?> requests</p>
        <p class="text-gray-500">R<?php echo number_format($statusSummary['failed']['total'], 2); ?></p>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold">Recent Transfers</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processed</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($withdrawals->num_rows > 0): ?>
                    <?php while ($row = $withdrawals->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['seller_name']) . ' (' . htmlspecialchars($row['seller_email']) . ')'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R<?php echo number_format($row['amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $row['status'] == 'completed' ? 'text-green-600' : ($row['status'] == 'failed' ? 'text-red-600' : 'text-yellow-600'); ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['transfer_code'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($row['paystack_reference'] ?? '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y, g:i A', strtotime($row['requested_at'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['processed_at'] ? date('M j, Y, g:i A', strtotime($row['processed_at'])) : '-'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No transfers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>