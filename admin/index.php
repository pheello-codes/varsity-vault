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

    $note_details_stmt = $conn->prepare("SELECT n.title, u.email AS seller_email, u.name AS seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.id = ? LIMIT 1");
    $note_details_stmt->bind_param("i", $note_id);
    $note_details_stmt->execute();
    $note_details = $note_details_stmt->get_result()->fetch_assoc();

    if ($action === 'reject') {
        if (empty($_POST['rejection_reason'])) {
            $message = "Rejection reason is required.";
        } else {
            $rejection_reason = trim($_POST['rejection_reason']);
            $update_stmt = $conn->prepare("UPDATE notes SET status = ?, rejection_reason = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $status, $rejection_reason, $note_id);
            $update_stmt->execute();
            if ($update_stmt->affected_rows >= 0 && $note_details) {
                send_template_email('note_rejected', $note_details['seller_email'], [
                    'seller_name' => $note_details['seller_name'],
                    'note_title' => $note_details['title'],
                    'reason' => $rejection_reason,
                ]);
            }
            $message = "Note rejected successfully.";
        }
    } else {
        $update_stmt = $conn->prepare("UPDATE notes SET status = ? WHERE id = ?");
        $update_stmt->bind_param("si", $status, $note_id);
        $update_stmt->execute();

        if ($update_stmt->affected_rows >= 0 && $note_details) {
            send_template_email('note_approved', $note_details['seller_email'], [
                'seller_name' => $note_details['seller_name'],
                'note_title' => $note_details['title'],
            ]);
        }
        $message = "Note " . ($action == 'approve' ? 'approved' : 'rejected') . " successfully.";
    }
}

// Handle other admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == 'add_university') {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $stmt = $conn->prepare("INSERT INTO universities (name, type) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $type);
        $stmt->execute();
        $message = "University added.";
    } elseif ($action == 'delete_university') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM universities WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $message = "University deleted.";
    } elseif ($action == 'add_module') {
        $university_id = (int)$_POST['university_id'];
        $module_code = trim($_POST['module_code']);
        $module_name = trim($_POST['module_name']);
        $stmt = $conn->prepare("INSERT INTO modules (university_id, module_code, module_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $university_id, $module_code, $module_name);
        $stmt->execute();
        $message = "Module added.";
    } elseif ($action == 'import_modules') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $uni_name = trim($data[0]);
                $module_code = trim($data[1]);
                $module_name = trim($data[2]);
                // Get university id
                $uni_stmt = $conn->prepare("SELECT id FROM universities WHERE name = ?");
                $uni_stmt->bind_param("s", $uni_name);
                $uni_stmt->execute();
                $uni_result = $uni_stmt->get_result();
                if ($uni_result->num_rows > 0) {
                    $uni_id = $uni_result->fetch_assoc()['id'];
                    $stmt = $conn->prepare("INSERT INTO modules (university_id, module_code, module_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE module_name = VALUES(module_name)");
                    $stmt->bind_param("iss", $uni_id, $module_code, $module_name);
                    $stmt->execute();
                }
            }
            fclose($handle);
            $message = "Modules imported.";
        }
    }
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

$pending_count_stmt = $conn->prepare("SELECT COUNT(*) AS pending_approvals FROM notes WHERE status = 'pending'");
$pending_count_stmt->execute();
$pending_approvals = $pending_count_stmt->get_result()->fetch_assoc()['pending_approvals'] ?? 0;

$pending_withdrawals_stmt = $conn->prepare("SELECT COUNT(*) AS pending_withdrawals FROM withdrawals WHERE status IN ('processing', 'pending_funds')");
$pending_withdrawals_stmt->execute();
$pending_withdrawals = $pending_withdrawals_stmt->get_result()->fetch_assoc()['pending_withdrawals'] ?? 0;

$platform_revenue_stmt = $conn->prepare("SELECT COALESCE(SUM(commission_amount), 0) AS platform_revenue FROM seller_earnings");
$platform_revenue_stmt->execute();
$platform_revenue = $platform_revenue_stmt->get_result()->fetch_assoc()['platform_revenue'] ?? 0;

// Get rejected notes
$rejected_stmt = $conn->prepare("
    SELECT n.*, u.name as seller_name, u.email as seller_email
    FROM notes n
    JOIN users u ON n.seller_id = u.id
    WHERE n.status = 'rejected'
    ORDER BY n.created_at DESC
");
$rejected_stmt->execute();
$rejected_notes = $rejected_stmt->get_result();

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
$top_notes_result = $top_notes_stmt->get_result();
$top_notes = [];
while ($row = $top_notes_result->fetch_assoc()) {
    $top_notes[] = $row;
}
$chart_labels = json_encode(array_map(function ($item) {
    return htmlspecialchars($item['title'], ENT_QUOTES);
}, $top_notes));
$chart_values = json_encode(array_map(function ($item) {
    return (int) ($item['sales_count'] ?? 0);
}, $top_notes));
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

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-sm text-gray-500">Total Sales</p>
        <p class="text-3xl font-bold text-blue-600"><?php echo $sales_data['total_purchases'] ?? 0; ?></p>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-sm text-gray-500">Pending Approvals</p>
        <p class="text-3xl font-bold text-yellow-600"><?php echo $pending_approvals; ?></p>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-sm text-gray-500">Pending Withdrawals</p>
        <p class="text-3xl font-bold text-red-600"><?php echo $pending_withdrawals; ?></p>
    </div>
    <div class="bg-white rounded-lg shadow-md p-6">
        <p class="text-sm text-gray-500">Platform Revenue</p>
        <p class="text-3xl font-bold text-green-600">R<?php echo number_format($platform_revenue, 2); ?></p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="mb-6">
    <nav class="flex space-x-4" aria-label="Tabs">
        <button onclick="showTab('pending')" class="tab-button active bg-blue-600 text-white px-4 py-2 rounded-md" data-tab="pending">Pending Notes</button>        <button onclick="showTab('rejected')" class="tab-button bg-gray-200 text-gray-700 px-4 py-2 rounded-md" data-tab="rejected">Rejected Notes</button>        <button onclick="showTab('users')" class="tab-button bg-gray-200 text-gray-700 px-4 py-2 rounded-md" data-tab="users">User Activity</button>
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
                                        <a href="../download.php?note_id=<?php echo $note['id']; ?>" target="_blank" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition duration-300 inline-block mr-2">View PDF</a>
                                    <?php endif; ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition duration-300 mr-2">Approve</button>
                                    </form>
                                    <button type="button" onclick="openRejectModal(<?php echo $note['id']; ?>)" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition duration-300">Reject</button>
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

<!-- Rejected Notes Tab -->
<div id="rejected-tab" class="tab-content hidden">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold">Rejected Notes (<?php echo $rejected_notes->num_rows; ?>)</h2>
        </div>

        <?php if ($rejected_notes->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">University</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejection Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected At</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($note = $rejected_notes->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($note['title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($note['description'], 0, 50)) . (strlen($note['description']) > 50 ? '...' : ''); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['module_code']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['university']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($note['seller_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($note['seller_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['rejection_reason'] ?? 'No reason provided'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y H:i', strtotime($note['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="px-6 py-8 text-center">
                <p class="text-gray-500">No rejected notes.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
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
        <div class="p-6">
            <canvas id="salesChart" class="w-full h-64"></canvas>
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
                    <?php foreach ($top_notes as $note): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($note['title']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['module_code']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($note['seller_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R<?php echo number_format($note['price'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $note['sales_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($note['avg_rating'] ?? 0, 1); ?>/5</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Manage Categories Tab -->
<div id="categories-tab" class="tab-content hidden">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Manage Universities & Modules</h2>
        
        <!-- Add University Form -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Add University</h3>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="add_university">
                <input type="text" name="name" placeholder="University Name" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
                <select name="type" required class="px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="university">University</option>
                    <option value="college">College</option>
                    <option value="tvet">TVET</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add</button>
            </form>
        </div>
        
        <!-- Universities List -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Universities</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $uni_stmt = $conn->prepare("SELECT * FROM universities ORDER BY name");
                        $uni_stmt->execute();
                        $unis = $uni_stmt->get_result();
                        while ($uni = $unis->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($uni['name']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($uni['type']); ?></td>
                                <td class="px-4 py-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_university">
                                        <input type="hidden" name="id" value="<?php echo $uni['id']; ?>">
                                        <button type="submit" onclick="return confirm('Delete this university?')" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Add Module Form -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Add Module</h3>
            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="add_module">
                <select name="university_id" required class="px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Select University</option>
                    <?php
                    $uni_stmt->execute();
                    $unis = $uni_stmt->get_result();
                    while ($uni = $unis->fetch_assoc()): ?>
                        <option value="<?php echo $uni['id']; ?>"><?php echo htmlspecialchars($uni['name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <input type="text" name="module_code" placeholder="Module Code" required class="px-3 py-2 border border-gray-300 rounded-lg">
                <input type="text" name="module_name" placeholder="Module Name" required class="px-3 py-2 border border-gray-300 rounded-lg">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add</button>
            </form>
        </div>
        
        <!-- Bulk Import -->
        <div>
            <h3 class="text-lg font-semibold mb-2">Bulk Import Modules (CSV)</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_modules">
                <input type="file" name="csv_file" accept=".csv" required class="mb-2">
                <p class="text-sm text-gray-600 mb-2">CSV format: university_name,module_code,module_name</p>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Import</button>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" id="my-modal">
    <div class="relative top-20 mx-auto w-full max-w-md p-5 border shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg font-medium text-gray-900" id="modal-title">Reject Note</h3>
            <div class="mt-2 px-7 py-3">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="note_id" id="rejectNoteId" value="">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 text-left">Rejection Reason (Required)</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required></textarea>
                    <div class="flex items-center px-4 py-3">
                        <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300 mr-2">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">Reject Note</button>
                    </div>
                </form>
            </div>
        </div>
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

function openRejectModal(noteId) {
    document.getElementById('rejectNoteId').value = noteId;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejection_reason').value = '';
}
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo $chart_labels ?? '[]'; ?>,
                datasets: [{
                    label: 'Sales Count',
                    data: <?php echo $chart_values ?? '[]'; ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>