<?php
$page_title = "Admin Panel";
include '../includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user['is_admin']) {
    header("Location: ../index.php");
    exit();
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['note_id'])) {
    $action = $_POST['action'];
    $note_id = (int)$_POST['note_id'];

    $status = ($action == 'approve') ? 'approved' : 'rejected';

    $update_stmt = $conn->prepare("UPDATE notes SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $status, $note_id);
    $update_stmt->execute();

    $message = "Note " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully.";
}

// Get pending notes
$pending_stmt = $conn->prepare("
    SELECT n.*, u.name as seller_name, u.email as seller_email
    FROM notes n
    JOIN users u ON n.seller_id = u.id
    WHERE n.status = 'pending'
    ORDER BY n.created_at DESC
");
$pending_stmt->execute();
$pending_notes = $pending_stmt->get_result();

// Get user activity data
$user_activity_stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.created_at, u.is_admin,
           COUNT(DISTINCT n.id) as notes_count,
           COUNT(DISTINCT p.id) as purchases_count,
           COUNT(DISTINCT r.id) as reviews_count
    FROM users u
    LEFT JOIN notes n ON u.id = n.seller_id
    LEFT JOIN purchases p ON u.id = p.user_id
    LEFT JOIN reviews r ON u.id = r.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$user_activity_stmt->execute();
$user_activity = $user_activity_stmt->get_result();

// Get sales reports
$sales_stmt = $conn->prepare("
    SELECT 
        COUNT(p.id) as total_purchases,
        SUM(n.price) as total_revenue,
        AVG(r.rating) as avg_rating,
        COUNT(DISTINCT p.user_id) as unique_buyers,
        COUNT(DISTINCT n.seller_id) as unique_sellers
    FROM purchases p
    JOIN notes n ON p.note_id = n.id
    LEFT JOIN reviews r ON p.note_id = r.note_id
");
$sales_stmt->execute();
$sales_data = $sales_stmt->get_result()->fetch_assoc();

// Get top-selling notes
$top_notes_stmt = $conn->prepare("
    SELECT n.title, n.module_code, n.university, n.price, u.name as seller_name,
           COUNT(p.id) as sales_count, AVG(r.rating) as avg_rating
    FROM notes n
    JOIN users u ON n.seller_id = u.id
    LEFT JOIN purchases p ON n.id = p.note_id
    LEFT JOIN reviews r ON n.id = r.note_id
    WHERE n.status = 'approved'
    GROUP BY n.id
    ORDER BY sales_count DESC
    LIMIT 10
");
$top_notes_stmt->execute();
$top_notes = $top_notes_stmt->get_result();
?>

<?php include '../includes/header.php'; ?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Admin Panel</h1>
    <p class="text-gray-600 mt-2">Manage the platform: approve notes, monitor users, view reports</p>
</div>

<?php if (isset($message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="mb-6">
    <nav class="flex space-x-4" aria-label="Tabs">
        <button onclick="showTab('pending')" class="tab-button active bg-blue-600 text-white px-4 py-2 rounded-md" data-tab="pending">Pending Notes</button>
        <button onclick="showTab('users')" class="tab-button bg-gray-200 text-gray-700 px-4 py-2 rounded-md" data-tab="users">User Activity</button>
        <button onclick="showTab('reports')" class="tab-button bg-gray-200 text-gray-700 px-4 py-2 rounded-md" data-tab="reports">Sales Reports</button>
        <button onclick="showTab('categories')" class="tab-button bg-gray-200 text-gray-700 px-4 py-2 rounded-md" data-tab="categories">Manage Categories</button>
        <a href="paystack-dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Paystack Dashboard</a>
    </nav>
</div>

<!-- Pending Notes Tab -->
<div id="pending-tab" class="tab-content">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold">Pending Notes (<?php echo $pending_notes->num_rows; ?>)</h2>
        </div>

        <?php if ($pending_notes->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">University</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($note = $pending_notes->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($note['title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($note['description'], 0, 50)) . (strlen($note['description']) > 50 ? '...' : ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['module_code']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R<?php echo number_format($note['price'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($note['seller_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($note['seller_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($note['price'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($note['file_path']): ?>
                                        <a href="<?php echo htmlspecialchars($note['file_path']); ?>" target="_blank" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition duration-300 inline-block mr-2">View PDF</a>
                                    <?php endif; ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition duration-300 mr-2">Approve</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition duration-300">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="px-6 py-8 text-center">
                <p class="text-gray-500">No pending notes to review.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Activity Tab -->
<div id="users-tab" class="tab-content hidden">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold">User Activity</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchases</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reviews</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($user = $user_activity->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['is_admin'] ? 'Admin' : 'User'; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['notes_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['purchases_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $user['reviews_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sales Reports Tab -->
<div id="reports-tab" class="tab-content hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900">Total Purchases</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo $sales_data['total_purchases'] ?? 0; ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900">Total Revenue</h3>
            <p class="text-3xl font-bold text-green-600">R<?php echo number_format($sales_data['total_revenue'] ?? 0, 2); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900">Average Rating</h3>
            <p class="text-3xl font-bold text-yellow-600"><?php echo number_format($sales_data['avg_rating'] ?? 0, 1); ?>/5</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900">Unique Users</h3>
            <p class="text-3xl font-bold text-purple-600"><?php echo ($sales_data['unique_buyers'] ?? 0) + ($sales_data['unique_sellers'] ?? 0); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold">Top-Selling Notes</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($note = $top_notes->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($note['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['module_code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['seller_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R<?php echo number_format($note['price'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $note['sales_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($note['avg_rating'] ?? 0, 1); ?>/5</td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Categories Tab -->
<div id="categories-tab" class="tab-content hidden">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Manage Universities & Modules</h2>
        <p class="text-gray-600 mb-4">Currently, universities and modules are entered freely by sellers. To manage them centrally, we can add predefined lists in the future.</p>
        <p class="text-gray-600">For now, monitor the variety in the User Activity and Sales Reports tabs.</p>
        <!-- Placeholder for future CRUD functionality -->
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.add('hidden'));

    // Remove active class from all buttons
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => button.classList.remove('active', 'bg-blue-600', 'text-white'));
    buttons.forEach(button => button.classList.add('bg-gray-200', 'text-gray-700'));

    // Show selected tab
    document.getElementById(tabName + '-tab').classList.remove('hidden');

    // Add active class to clicked button
    const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
    activeButton.classList.add('active', 'bg-blue-600', 'text-white');
    activeButton.classList.remove('bg-gray-200', 'text-gray-700');
}
</script>

<?php include '../includes/footer.php'; ?>