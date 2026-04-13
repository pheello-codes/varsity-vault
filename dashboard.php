<?php
$page_title = "Dashboard";
include 'includes/config.php';
include 'includes/auth_check.php';

// Handle note deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $note_id = (int)$_POST['note_id'];
    
    // Verify the note belongs to the user
    $verify_stmt = $conn->prepare("SELECT seller_id FROM notes WHERE id = ?");
    $verify_stmt->bind_param("i", $note_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    $note = $result->fetch_assoc();
    
    if ($note && $note['seller_id'] == $_SESSION['user_id']) {
        // Delete the note
        $delete_stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $delete_stmt->bind_param("i", $note_id);
        $delete_stmt->execute();
        
        // Delete associated purchases and reviews
        $conn->query("DELETE FROM purchases WHERE note_id = $note_id");
        $conn->query("DELETE FROM reviews WHERE note_id = $note_id");
        
        $delete_message = "Note deleted successfully.";
    }
}

// Get user's purchases
$purchase_stmt = $conn->prepare("
    SELECT p.purchase_date, n.title, n.module_code, n.university, n.file_path
    FROM purchases p
    JOIN notes n ON p.note_id = n.id
    WHERE p.user_id = ?
    ORDER BY p.purchase_date DESC
");
$purchase_stmt->bind_param("i", $_SESSION['user_id']);
$purchase_stmt->execute();
$purchases = $purchase_stmt->get_result();

// Get user's listings
$listing_stmt = $conn->prepare("SELECT * FROM notes WHERE seller_id = ? ORDER BY created_at DESC");
$listing_stmt->bind_param("i", $_SESSION['user_id']);
$listing_stmt->execute();
$listings = $listing_stmt->get_result();
?>

<?php include 'includes/header.php'; ?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600 mt-2">Manage your purchases and listings</p>
</div>

<?php if (isset($delete_message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($delete_message); ?>
    </div>
    <?php // Refresh the listings after deletion
    $listing_stmt = $conn->prepare("SELECT * FROM notes WHERE seller_id = ? ORDER BY created_at DESC");
    $listing_stmt->bind_param("i", $_SESSION['user_id']);
    $listing_stmt->execute();
    $listings = $listing_stmt->get_result();
    ?>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- My Library -->
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
                            <a href="<?php echo htmlspecialchars($purchase['file_path']); ?>" target="_blank" class="inline-block mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-300">
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

    <!-- My Listings -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-semibold mb-6">My Listings</h2>

        <?php if ($listings->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($listing = $listings->fetch_assoc()): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($listing['title']); ?></h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($listing['module_code']); ?> - <?php echo htmlspecialchars($listing['university']); ?></p>
                        <p class="text-sm text-gray-500">$<?php echo number_format($listing['price'], 2); ?> - Status: 
                            <span class="font-semibold <?php echo $listing['status'] == 'approved' ? 'text-green-600' : ($listing['status'] == 'pending' ? 'text-yellow-600' : 'text-red-600'); ?>">
                                <?php echo ucfirst($listing['status']); ?>
                            </span>
                        </p>
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