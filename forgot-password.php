<?php
$page_title = 'Forgot Password';
include 'includes/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = 'Please enter your registered email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, name FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $insert = $conn->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)');
            $insert->bind_param('sss', $email, $token, $expires_at);
            $insert->execute();

            $reset_url = SITE_URL . '/reset-password.php?token=' . urlencode($token);
            $email_sent = send_template_email('password_reset', $email, [
                'name' => $user['name'] ?? 'Student',
                'reset_url' => $reset_url,
            ]);
            
            if (!$email_sent) {
                error_log("Failed to send password reset email to $email");
            }
        }

        $success = true;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Forgot Password</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            If an account exists for that email address, a password reset link has been sent.
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
                    <label for="email" class="block text-gray-700 font-semibold mb-2">Email Address *</label>
                    <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                    Send Reset Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600">Remembered your password? <a href="login.php" class="text-blue-600 hover:underline">Login here</a></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
