<?php
$page_title = 'Terms of Service';
include 'includes/config.php';
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
    <h1 class="text-4xl font-bold text-gray-900 mb-6">Terms of Service</h1>
    <p class="text-gray-600 mb-6">These terms govern your use of Varsity Vault. By accessing or using the service, you agree to comply with these terms.</p>

    <section class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">Commission and Payouts</h2>
        <p class="text-gray-700 mb-3">Varsity Vault retains a commission of <?php echo (PLATFORM_COMMISSION_RATE * 100); ?>% on all note sales. Seller earnings are calculated after the commission is deducted.</p>
        <p class="text-gray-700">Withdrawals are processed through Paystack. Sellers are responsible for providing accurate banking details and any fees levied by third-party payment providers.</p>
    </section>

    <section class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">User Conduct</h2>
        <ul class="list-disc list-inside text-gray-700 space-y-2">
            <li>Users must only upload notes they have the right to sell and must not infringe third-party copyrights.</li>
            <li>Fraudulent activity, harassment, or misuse of the platform is prohibited.</li>
            <li>All content must comply with applicable laws and Varsity Vault policies.</li>
            <li>Users are responsible for keeping their account credentials secure.</li>
        </ul>
    </section>

    <section>
        <h2 class="text-2xl font-semibold text-gray-900 mb-3">Platform Responsibility</h2>
        <p class="text-gray-700 mb-3">Varsity Vault provides a marketplace for selling and purchasing academic notes. We do not guarantee the academic suitability of any material.</p>
        <p class="text-gray-700">We reserve the right to remove content that violates our terms or applicable law, and to suspend or terminate accounts for violations.</p>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
