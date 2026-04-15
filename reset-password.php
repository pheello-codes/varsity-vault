<?php
$page_title = 'Reset Password';
include 'includes/config.php';

$errors = [];
$success = false;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: forgot-password.php');
    exit();
}

$token_stmt = $conn->prepare('SELECT pr.email, u.name FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.token = ? AND pr.expires_at >= NOW() LIMIT 1');
$token_stmt->bind_param('s', $token);
$token_stmt->execute();
$token_data = $token_stmt->get_result()->fetch_assoc();

if (!$token_data) {
    $errors[] = 'This password reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $errors[] = 'Please enter and confirm your new password.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
        $update->bind_param('ss', $hashed_password, $token_data['email']);
        $update->execute();

        $delete = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
        $delete->bind_param('s', $token_data['email']);
        $delete->execute();

        $success = true;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Reset Password</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            Your password has been reset successfully. <a href="login.php" class="underline">Login now</a>.
        </div>
    <?php else: ?>
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
                    <label for="password" class="block text-gray-700 font-semibold mb-2">New Password *</label>
                    <input type="password" id="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-semibold mb-2">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                    Reset Password
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
