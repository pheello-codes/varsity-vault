<?php
$page_title = "Upload Notes";
include 'includes/config.php';
include 'includes/auth_check.php';

// Get universities
$universities_stmt = $conn->prepare("SELECT id, name FROM universities ORDER BY name");
$universities_stmt->execute();
$universities = $universities_stmt->get_result();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $module_code = trim($_POST['module_code']);
    $university = trim($_POST['university']);
    $price = trim($_POST['price']);
    $description = trim($_POST['description']);

    // Validate inputs
    if (empty($title)) $errors[] = "Title is required.";
    if (empty($module_code)) $errors[] = "Module code is required.";
    if (empty($university)) $errors[] = "University is required.";
    if (empty($price) || !is_numeric($price) || $price <= 0) $errors[] = "Valid price is required.";

    // Handle file upload
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        $file = $_FILES['pdf_file'];

        // Check file type
        $allowed_types = ['application/pdf'];
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only PDF files are allowed.";
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = "File size must be less than 10MB.";
        }

        if (empty($errors)) {
            // Generate unique filename
            $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($file['name']));
            $filepath = 'uploads/notes/' . $filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO notes (title, module_code, university, price, description, file_path, seller_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("ssssssi", $title, $module_code, $university, $price, $description, $filepath, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $errors[] = "Database error: " . $conn->error;
                    // Delete uploaded file if DB insert failed
                    unlink($filepath);
                }
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    } else {
        $errors[] = "PDF file is required.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Upload Study Notes</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Success!</strong> Your notes have been uploaded and are pending approval. You'll be notified once they're reviewed.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" enctype="multipart/form-data" onsubmit="return validateUploadForm()" class="space-y-6">
            <div>
                <label for="title" class="block text-gray-700 font-semibold mb-2">Title *</label>
                <input type="text" id="title" name="title" required class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="title-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-4">
                <label for="university" class="block text-gray-700 font-semibold mb-2">University *</label>
                <select id="university" name="university" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select University</option>
                    <?php while ($uni = $universities->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($uni['name']); ?>"><?php echo htmlspecialchars($uni['name']); ?></option>
                    <?php endwhile; ?>
                </select>
                <p id="university-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-4">
                <label for="module_code" class="block text-gray-700 font-semibold mb-2">Module Code *</label>
                <input type="text" id="module_code" name="module_code" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="module-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-4">
                <label for="price" class="block text-gray-700 font-semibold mb-2">Price (R) *</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="price-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-4">
                <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                <textarea id="description" name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div class="mb-6">
                <label for="pdf_file" class="block text-gray-700 font-semibold mb-2">PDF File *</label>
                <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="file-error" class="text-red-500 text-sm mt-1 hidden"></p>
                <p class="text-sm text-gray-500 mt-1">Maximum file size: 10MB. Only PDF files are allowed.</p>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                Upload Notes
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>