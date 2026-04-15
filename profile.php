<?php
$page_title = "Profile";
include 'includes/config.php';
include 'includes/auth_check.php';
include 'includes/paystack.php';

$errors = [];
$success = false;

// Get current user data
$stmt = $conn->prepare("SELECT name, email, bank_code, account_number, account_name, recipient_code FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$bankList = [];
$bankListResult = getBankList();
if ($bankListResult['status']) {
    $bankList = $bankListResult['data'];
} elseif (!empty($bankListResult['data'])) {
    // Use fallback list if available
    $bankList = $bankListResult['data'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $bank_code = trim($_POST['bank_code']);
    $account_number = trim($_POST['account_number']);
    $account_name = trim($_POST['account_name']);
    $recipient_code = $user['recipient_code'];

    // Validate inputs
    if (empty($name) || empty($email)) {
        $errors[] = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        $email_check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check_stmt->bind_param("si", $email, $_SESSION['user_id']);
        $email_check_stmt->execute();

        if ($email_check_stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already taken.";
        } else {
            if (!empty($bank_code) && !empty($account_number)) {
                $recipientResult = createTransferRecipient($name, $account_number, $bank_code);
                if ($recipientResult['status']) {
                    $recipient_code = $recipientResult['data']['recipient_code'] ?? $recipient_code;
                    $account_name = $recipientResult['data']['details']['account_name'] ?? $account_name;
                } else {
                    $errors[] = 'Paystack recipient creation failed: ' . htmlspecialchars($recipientResult['message']);
                }
            }

            if (empty($errors)) {
                $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, bank_code = ?, account_number = ?, account_name = ?, recipient_code = ? WHERE id = ?");
                $update_stmt->bind_param("ssssssi", $name, $email, $bank_code, $account_number, $account_name, $recipient_code, $_SESSION['user_id']);
                $update_stmt->execute();

                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        $errors[] = "Current password is required to change password.";
                    } elseif (strlen($new_password) < 6) {
                        $errors[] = "New password must be at least 6 characters long.";
                    } elseif ($new_password !== $confirm_password) {
                        $errors[] = "New passwords do not match.";
                    } else {
                        $password_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                        $password_stmt->bind_param("i", $_SESSION['user_id']);
                        $password_stmt->execute();
                        $current_hash = $password_stmt->get_result()->fetch_assoc()['password'];

                        if (!password_verify($current_password, $current_hash)) {
                            $errors[] = "Current password is incorrect.";
                        } else {
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
                    $user = [
                        'name' => $name,
                        'email' => $email,
                        'bank_code' => $bank_code,
                        'account_number' => $account_number,
                        'account_name' => $account_name,
                        'recipient_code' => $recipient_code
                    ];
                }
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-2xl mx-auto">
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
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-semibold mb-2">Email *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="bank_code" class="block text-gray-700 font-semibold mb-2">Bank *</label>
                <select id="bank_code" name="bank_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    <option value="">Select your bank</option>
                    <?php foreach ($bankList as $bank): ?>
                        <option value="<?php echo htmlspecialchars($bank['code']); ?>" <?php echo (isset($user['bank_code']) && $user['bank_code'] === $bank['code']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($bank['name']); ?> (<?php echo htmlspecialchars($bank['code']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="account_number" class="block text-gray-700 font-semibold mb-2">Account Number *</label>
                <input type="text" id="account_number" name="account_number" value="<?php echo htmlspecialchars($user['account_number'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="account_name" class="block text-gray-700 font-semibold mb-2">Account Name</label>
                <input type="text" id="account_name" name="account_name" value="<?php echo htmlspecialchars($user['account_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <?php if (!empty($user['recipient_code'])): ?>
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    Paystack recipient code: <strong><?php echo htmlspecialchars($user['recipient_code']); ?></strong>
                </div>
            <?php endif; ?>

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
                Update Profile & Bank Details
            </button>
        </form>

        <?php if (!$bankListResult['status'] && empty($bankListResult['message']) || (strpos($bankListResult['message'] ?? '', 'fallback') !== false)): ?>
            <div class="mt-4 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                Using an offline bank list. For the most up-to-date banks, ensure Paystack API is accessible.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>