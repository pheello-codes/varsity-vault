<?php
$page_title = "Varsity Vault - Buy and Sell Study Notes";
include 'includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$university_filter = isset($_GET['university']) ? $_GET['university'] : '';

// Build query
$query = "SELECT n.*, u.name as seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.status = 'approved'";
$params = [];
$types = '';

if ($search) {
    $query .= " AND n.module_code LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

if ($university_filter) {
    $query .= " AND n.university = ?";
    $params[] = $university_filter;
    $types .= 's';
}

$query .= " ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get unique universities for filter dropdown
$university_query = "SELECT DISTINCT university FROM notes WHERE status = 'approved' ORDER BY university";
$university_result = $conn->query($university_query);
?>

<?php include 'includes/header.php'; ?>

<div class="mb-8">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">Welcome to Varsity Vault</h1>
    <p class="text-xl text-gray-600">Buy and sell high-quality study notes from students across universities.</p>
</div>

<!-- Search and Filter -->
<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <form method="GET" class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <input type="text" name="search" placeholder="Search by module code..." value="<?php echo htmlspecialchars($search); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="md:w-64">
            <select name="university" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Universities</option>
                <?php while ($uni = $university_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($uni['university']); ?>" <?php echo $university_filter == $uni['university'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($uni['university']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-300">Search</button>
    </form>
</div>

<!-- Notes Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if ($result->num_rows > 0): ?>
        <?php while ($note = $result->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden card-hover">
                <div class="h-48 bg-gray-200 flex items-center justify-center">
                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($note['title']); ?></h3>
                    <p class="text-gray-600 mb-1"><strong>Module:</strong> <?php echo htmlspecialchars($note['module_code']); ?></p>
                    <p class="text-gray-600 mb-1"><strong>University:</strong> <?php echo htmlspecialchars($note['university']); ?></p>
                    <p class="text-gray-600 mb-1"><strong>Seller:</strong> <?php echo htmlspecialchars($note['seller_name']); ?></p>
                    <p class="text-2xl font-bold text-blue-600 mb-4">$<?php echo number_format($note['price'], 2); ?></p>
                    <a href="product.php?id=<?php echo $note['id']; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300 inline-block w-full text-center">View Details</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-full text-center py-12">
            <p class="text-gray-500 text-lg">No notes found matching your criteria.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>