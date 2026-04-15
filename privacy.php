<?php
$page_title = 'Privacy Policy';
include 'includes/config.php';
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
    <h1 class="text-4xl font-bold text-gray-900 mb-6">Privacy Policy</h1>
    <p class="text-gray-600 mb-6">At Varsity Vault, we take your privacy seriously. This policy explains how we collect, use, and protect your personal information.</p>

    <section class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">Information We Collect</h2>
        <ul class="list-disc list-inside text-gray-700 space-y-2">
            <li>Account details such as name, email address, and password.</li>
            <li>Billing and payout information including bank details.</li>
            <li>Purchase history, downloads, and transaction records.</li>
            <li>Support and communication data when you contact us.</li>
        </ul>
    </section>

    <section class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">How We Use Personal Data</h2>
        <p class="text-gray-700 mb-3">We use your information to:</p>
        <ul class="list-disc list-inside text-gray-700 space-y-2">
            <li>Deliver payments and process purchases.</li>
            <li>Send account notifications, purchase confirmations, and password reset messages.</li>
            <li>Maintain the security of the platform and prevent fraud.</li>
            <li>Comply with applicable laws and regulatory requirements.</li>
        </ul>
    </section>

    <section class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">POPIA Compliance</h2>
        <p class="text-gray-700 mb-3">Varsity Vault is committed to complying with the Protection of Personal Information Act (POPIA). We collect and process personal information lawfully, minimally, and only for specified purposes.</p>
        <p class="text-gray-700">We implement reasonable technical and organizational measures to protect your data from unauthorized access and misuse.</p>
    </section>

    <section>
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">Your Rights</h2>
        <p class="text-gray-700 mb-3">You have the right to access, correct, or delete your personal data. If you have questions about your privacy or want to update your information, contact our support team.</p>
        <p class="text-gray-700">For more information, please review our contact details on the website or send an email to <strong><?php echo htmlspecialchars(EMAIL_FROM_ADDRESS); ?></strong>.</p>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
