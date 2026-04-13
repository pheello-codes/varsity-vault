<?php
$page_title = "Register";
include 'includes/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $name, $email, $hashed_password);

            if ($insert_stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-md mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Register</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <strong>Success!</strong> Your account has been created. <a href="login.php" class="underline">Login here</a>
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
        <form method="POST" onsubmit="return validateRegistrationForm()">
            <div class="mb-4">
                <label for="name" class="block text-gray-700 font-semibold mb-2">Full Name *</label>
                <input type="text" id="name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <div class="mb-4">
                <label for="email" class="block text-gray-700 font-semibold mb-2">Email *</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="email-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700 font-semibold mb-2">Password *</label>
                <input type="password" id="password" name="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="password-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 font-semibold mb-2">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p id="confirm-password-error" class="text-red-500 text-sm mt-1 hidden"></p>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 w-full text-lg font-semibold">
                Register
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">Already have an account? <a href="login.php" class="text-blue-600 hover:underline">Login here</a></p>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>