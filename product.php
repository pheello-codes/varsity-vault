<?php
$page_title = "Product Details";
include 'includes/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$note_id = (int)$_GET['id'];

// Get note details
$stmt = $conn->prepare("SELECT n.*, u.name as seller_name FROM notes n JOIN users u ON n.seller_id = u.id WHERE n.id = ? AND n.status = 'approved'");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$note = $result->fetch_assoc();

// Get reviews
$review_stmt = $conn->prepare("SELECT r.*, u.name as reviewer_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.note_id = ? ORDER BY r.created_at DESC");
$review_stmt->bind_param("i", $note_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();

// Check if user has purchased this note
$has_purchased = false;
$has_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $purchase_stmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND note_id = ?");
    $purchase_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
    $purchase_stmt->execute();
    $has_purchased = $purchase_stmt->get_result()->num_rows > 0;

    if ($has_purchased) {
        $review_stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND note_id = ?");
        $review_stmt->bind_param("ii", $_SESSION['user_id'], $note_id);
        $review_stmt->execute();
        $has_reviewed = $review_stmt->get_result()->num_rows > 0;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Product Image -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="h-96 bg-gray-200 flex items-center justify-center">
            <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
    </div>

    <!-- Product Details -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($note['title']); ?></h1>

        <div class="mb-4">
            <p class="text-gray-600"><strong>Module Code:</strong> <?php echo htmlspecialchars($note['module_code']); ?></p>
            <p class="text-gray-600"><strong>University:</strong> <?php echo htmlspecialchars($note['university']); ?></p>
            <p class="text-gray-600"><strong>Seller:</strong> <?php echo htmlspecialchars($note['seller_name']); ?></p>
        </div>

        <p class="text-4xl font-bold text-blue-600 mb-6">$<?php echo number_format($note['price'], 2); ?></p>

        <p class="text-gray-700 mb-6"><?php echo nl2br(htmlspecialchars($note['description'])); ?></p>

        <?php if (isset($_SESSION['user_id'])): ?>
            <button onclick="addToCart(<?php echo $note['id']; ?>, '<?php echo addslashes($note['title']); ?>', <?php echo $note['price']; ?>)" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                Add to Cart
            </button>
        <?php else: ?>
            <a href="login.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold text-center inline-block">Login to Purchase</a>
        <?php endif; ?>

        <?php if ($has_purchased): ?>
            <div class="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                You have purchased this note. <a href="dashboard.php" class="underline">View in My Library</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reviews Section -->
<div class="mt-12 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Reviews</h2>

    <?php if ($reviews->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="border-b border-gray-200 pb-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                        <div class="flex items-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-5 h-5 <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    <p class="text-sm text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-gray-500">No reviews yet.</p>
    <?php endif; ?>

    <?php if ($has_purchased && !$has_reviewed): ?>
        <!-- Add Review Form -->
        <div class="mt-8 border-t pt-6">
            <h3 class="text-xl font-semibold mb-4">Write a Review</h3>
            <form method="POST" action="review.php">
                <input type="hidden" name="note_id" value="<?php echo $note_id; ?>">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Rating</label>
                    <select name="rating" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select rating</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Comment</label>
                    <textarea name="comment" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-300">Submit Review</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>