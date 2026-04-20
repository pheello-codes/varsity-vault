<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Varsity Vault'; ?></title>
    <link rel="stylesheet" href="/varsity-vault/assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-blue-600 text-white shadow-lg relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <button id="mobile-menu-toggle" class="block md:hidden inline-flex items-center justify-center w-11 h-11 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Open navigation menu" onclick="toggleMenu()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <a href="/varsity-vault/index.php" class="text-xl font-bold">Varsity Vault</a>
                </div>

                <div class="hidden md:flex md:items-center md:space-x-3">
                    <a href="/varsity-vault/index.php" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
                    <a href="/varsity-vault/upload.php" class="hover:bg-blue-700 px-3 py-2 rounded">Sell Notes</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        try {
                            // Check if user is admin
                            $admin_check = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                            if (!$admin_check) {
                                throw new Exception('Failed to prepare admin check: ' . $conn->error);
                            }
                            $admin_check->bind_param("i", $_SESSION['user_id']);
                            $admin_check->execute();
                            $admin_result = $admin_check->get_result()->fetch_assoc();
                            $is_admin = $admin_result['is_admin'] ?? false;

                            // Get cart count
                            $cart_count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
                            if (!$cart_count_stmt) {
                                throw new Exception('Failed to prepare cart count: ' . $conn->error);
                            }
                            $cart_count_stmt->bind_param("i", $_SESSION['user_id']);
                            $cart_count_stmt->execute();
                            $cart_count_result = $cart_count_stmt->get_result()->fetch_assoc();
                            $cart_count = $cart_count_result['total_items'] ?? 0;
                        } catch (Exception $e) {
                            // Log error and set defaults
                            error_log('Header query error: ' . $e->getMessage());
                            $is_admin = false;
                            $cart_count = 0;
                        }
                        ?>
                        <?php if ($is_admin): ?>
                            <a href="/varsity-vault/admin/index.php" class="hover:bg-blue-700 px-3 py-2 rounded">Admin Panel</a>
                        <?php endif; ?>
                        <a href="/varsity-vault/dashboard.php" class="hover:bg-blue-700 px-3 py-2 rounded">Dashboard</a>
                        <a href="/varsity-vault/profile.php" class="hover:bg-blue-700 px-3 py-2 rounded">Profile</a>
                        <a href="/varsity-vault/logout.php" class="hover:bg-blue-700 px-3 py-2 rounded">Logout</a>
                    <?php else: ?>
                        <a href="/varsity-vault/login.php" class="hover:bg-blue-700 px-3 py-2 rounded">Login</a>
                        <a href="/varsity-vault/register.php" class="hover:bg-blue-700 px-3 py-2 rounded">Register</a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <a href="/varsity-vault/cart.php" class="relative inline-flex items-center justify-center min-w-[44px] min-h-[44px] hover:bg-blue-700 px-3 py-2 rounded">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.1 5H19M7 13l-1.1 5M7 13h10m0 0v8a2 2 0 01-2 2H9a2 2 0 01-2-2v-8z"></path>
                        </svg>
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full"><?php echo $cart_count ?? 0; ?></span>
                    </a>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-blue-700 border-t border-blue-500">
            <div class="px-4 py-4 space-y-1">
                <a href="/varsity-vault/index.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Home</a>
                <a href="/varsity-vault/upload.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Sell Notes</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($is_admin): ?>
                        <a href="/varsity-vault/admin/index.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Admin Panel</a>
                    <?php endif; ?>
                    <a href="/varsity-vault/dashboard.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Dashboard</a>
                    <a href="/varsity-vault/profile.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Profile</a>
                    <a href="/varsity-vault/logout.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Logout</a>
                <?php else: ?>
                    <a href="/varsity-vault/login.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Login</a>
                    <a href="/varsity-vault/register.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Register</a>
                <?php endif; ?>
                <a href="/varsity-vault/cart.php" class="block px-3 py-3 rounded-lg hover:bg-blue-600">Cart (<?php echo $cart_count ?? 0; ?>)</a>
            </div>
        </div>
    </nav>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (menuToggle && mobileMenu) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                mobileMenu.classList.toggle('hidden');
            });
        }
    });
</script>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">