<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Varsity Vault - Buy and Sell Study Notes";
include 'includes/config.php';

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$university_filter = isset($_GET['university']) ? $_GET['university'] : '';

// Build query
$query = "SELECT n.*, u.name as seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.status = 'approved'";
$params = [];
$types = '';

if ($search) {
    $query .= " AND (n.title LIKE ? OR n.module_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if ($university_filter) {
    $query .= " AND n.university = ?";
    $params[] = $university_filter;
    $types .= 's';
}

$query .= " ORDER BY n.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Get unique universities for filter dropdown
    $university_query = "SELECT DISTINCT university FROM notes WHERE status = 'approved' ORDER BY university";
    $university_result = $conn->query($university_query);
    if (!$university_result) {
        throw new Exception('Failed to execute university query: ' . $conn->error);
    }
} catch (Exception $e) {
    die('<div style="color: red; font-family: Arial, sans-serif; padding: 20px; border: 1px solid red; background-color: #ffe6e6; margin: 20px;">
        <h2>Database Query Error</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p>Please try again later or contact support if the problem persists.</p>
    </div>');
}
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
            <?php
            // Get rating for this note
            try {
                $rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE note_id = ?");
                if (!$rating_stmt) {
                    throw new Exception('Failed to prepare rating statement: ' . $conn->error);
                }
                $rating_stmt->bind_param("i", $note['id']);
                $rating_stmt->execute();
                $rating_result = $rating_stmt->get_result();
                $rating = $rating_result->fetch_assoc();
            } catch (Exception $e) {
                // Log error and set default rating
                error_log('Rating query error for note ' . $note['id'] . ': ' . $e->getMessage());
                $rating = ['avg_rating' => 0, 'review_count' => 0];
            }
            ?>
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
                    <p class="text-2xl font-bold text-blue-600 mb-2">R<?php echo number_format($note['price'], 2); ?></p>
                    <?php if ($rating['review_count'] > 0): ?>
                        <div class="flex items-center mb-4">
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 <?php echo $i <= round($rating['avg_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <span class="ml-2 text-sm text-gray-600">(<?php echo number_format($rating['avg_rating'], 1); ?>)</span>
                        </div>
                    <?php endif; ?>
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