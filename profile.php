<?php
$page_title = "Profile";
include 'includes/config.php';
include 'includes/auth_check.php';

$errors = [];
$success = false;

// Get current user data
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($name) || empty($email)) {
        $errors[] = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email is already taken by another user
        $email_check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check_stmt->bind_param("si", $email, $_SESSION['user_id']);
        $email_check_stmt->execute();

        if ($email_check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already taken.";
        } else {
            $update_fields = [];
            $update_types = "";
            $update_params = [];

            // Update name and email
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
            $update_stmt->execute();

            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $errors[] = "Current password is required to change password.";
                } elseif (strlen($new_password) < 6) {
                    $errors[] = "New password must be at least 6 characters long.";
                } elseif ($new_password !== $confirm_password) {
                    $errors[] = "New passwords do not match.";
                } else {
                    // Verify current password
                    $password_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $password_stmt->bind_param("i", $_SESSION['user_id']);
                    $password_stmt->execute();
                    $current_hash = $password_stmt->get_result()->fetch_assoc()['password'];

                    if (!password_verify($current_password, $current_hash)) {
                        $errors[] = "Current password is incorrect.";
                    } else {
                        // Update password
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $password_update_stmt->bind_param("si", $new_hash, $_SESSION['user_id']);
                        $password_update_stmt->execute();
                    }
                }
            }

            if (empty($errors)) {
                $success = true;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $user = ['name' => $name, 'email' => $email];
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Profile Settings</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Success!</strong> Your profile has been updated.
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
        <form method="POST">
            <div class="mb-4">
                <label for="name" class="block text-gray-700 font-semibold mb-2">Full Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-semibold mb-2">Email *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 font-semibold mb-2">Current Password (required for password change)</label>
                <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 font-semibold mb-2">New Password</label>
                <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 font-semibold mb-2">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                Update Profile
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>