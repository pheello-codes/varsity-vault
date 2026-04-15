<?php

if (!defined('EMAIL_FROM_ADDRESS')) {
    define('EMAIL_FROM_ADDRESS', getenv('EMAIL_FROM_ADDRESS') ?: 'no-reply@varsityvault.com');
}

if (!defined('EMAIL_FROM_NAME')) {
    define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'Varsity Vault');
}

if (!defined('EMAIL_USE_SMTP')) {
    define('EMAIL_USE_SMTP', getenv('EMAIL_USE_SMTP') === '1');
}

if (!defined('EMAIL_SMTP_HOST')) {
    define('EMAIL_SMTP_HOST', getenv('EMAIL_SMTP_HOST') ?: 'localhost');
}

if (!defined('EMAIL_SMTP_PORT')) {
    define('EMAIL_SMTP_PORT', getenv('EMAIL_SMTP_PORT') ?: 25);
}

function get_email_template($template, $data = []) {
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'Varsity Vault';
    $baseUrl = defined('SITE_URL') ? SITE_URL : '';
    switch ($template) {
        case 'purchase_confirmation':
            $title = $data['title'] ?? 'Purchase Confirmation';
            $total = $data['total'] ?? '0.00';
            $itemsHtml = $data['items_html'] ?? '';
            return [
                'subject' => 'Purchase confirmed at ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>Purchase Confirmation</h2>
                    <p>Hi " . htmlspecialchars($data['name'] ?? 'Customer') . ",</p>
                    <p>Thank you for your purchase. Your order is now complete and you can access your notes from your dashboard.</p>
                    <div style='background:#f8fafc;padding:16px;border-radius:8px;margin:16px 0;'>
                        <h3 style='margin-top:0;'>Order Summary</h3>
                        $itemsHtml
                        <p style='font-weight:600;'>Total Paid: R" . number_format($total, 2) . "</p>
                    </div>
                    <p><a href='" . $baseUrl . "/dashboard.php' style='color:#1d4ed8;'>Go to your dashboard</a></p>
                    <p>Thank you for choosing $siteName.</p>
                </body></html>"
            ];
        case 'sale_notification':
            return [
                'subject' => 'New sale on ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>New Sale Notification</h2>
                    <p>Hi " . htmlspecialchars($data['seller_name'] ?? 'Seller') . ",</p>
                    <p>Your note <strong>" . htmlspecialchars($data['note_title'] ?? '') . "</strong> has just been purchased.</p>
                    <p>Sale amount: R" . number_format($data['amount'] ?? 0, 2) . "</p>
                    <p>Buyer: " . htmlspecialchars($data['buyer_name'] ?? 'A customer') . "</p>
                    <p>You can review your earnings and payout status in your dashboard.</p>
                    <p><a href='" . $baseUrl . "/dashboard.php' style='color:#1d4ed8;'>View dashboard</a></p>
                </body></html>"
            ];
        case 'withdrawal_status':
            return [
                'subject' => 'Withdrawal status update from ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>Withdrawal Request Received</h2>
                    <p>Hi " . htmlspecialchars($data['name'] ?? 'Seller') . ",</p>
                    <p>Your withdrawal request for <strong>R" . number_format($data['amount'] ?? 0, 2) . "</strong> has been received.</p>
                    <p>Current status: <strong>" . htmlspecialchars($data['status'] ?? 'processing') . "</strong></p>
                    <p>You will be notified once the payout has been completed.</p>
                    <p><a href='" . $baseUrl . "/dashboard.php' style='color:#1d4ed8;'>View withdrawal history</a></p>
                </body></html>"
            ];
        case 'note_approved':
            return [
                'subject' => 'Your note has been approved on ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>Note Approved</h2>
                    <p>Hi " . htmlspecialchars($data['seller_name'] ?? 'Seller') . ",</p>
                    <p>Your note <strong>" . htmlspecialchars($data['note_title'] ?? '') . "</strong> has been approved and is now live on the platform.</p>
                    <p>Students can now purchase your note and you will earn from each sale.</p>
                    <p><a href='" . $baseUrl . "/dashboard.php' style='color:#1d4ed8;'>Go to your dashboard</a></p>
                </body></html>"
            ];
        case 'note_rejected':
            return [
                'subject' => 'Your note was rejected on ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>Note Rejected</h2>
                    <p>Hi " . htmlspecialchars($data['seller_name'] ?? 'Seller') . ",</p>
                    <p>Your note <strong>" . htmlspecialchars($data['note_title'] ?? '') . "</strong> was rejected.</p>
                    <p>Reason: " . nl2br(htmlspecialchars($data['reason'] ?? 'Under review by our team.')) . "</p>
                    <p>Please check the note details and resubmit with any required changes.</p>
                    <p><a href='" . $baseUrl . "/upload.php' style='color:#1d4ed8;'>Review your uploads</a></p>
                </body></html>"
            ];
        case 'password_reset':
            return [
                'subject' => 'Password reset request for ' . $siteName,
                'body' => "<html><body style='font-family:Arial,sans-serif;color:#333;'>
                    <h2 style='color:#1d4ed8;'>Password Reset Request</h2>
                    <p>Hi " . htmlspecialchars($data['name'] ?? 'User') . ",</p>
                    <p>We received a request to reset your password. Click the button below to choose a new password. This link will expire in one hour.</p>
                    <p><a href='" . htmlspecialchars($data['reset_url'] ?? $baseUrl) . "' style='display:inline-block;padding:12px 20px;background:#1d4ed8;color:#fff;border-radius:6px;text-decoration:none;'>Reset Password</a></p>
                    <p>If you did not request a password reset, you can ignore this email.</p>
                </body></html>"
            ];
        default:
            return [
                'subject' => 'Notification from ' . $siteName,
                'body' => "<html><body><p>This is a message from $siteName.</p></body></html>"
            ];
    }
}

function send_email($to, $subject, $htmlBody) {
    if (EMAIL_USE_SMTP) {
        ini_set('SMTP', EMAIL_SMTP_HOST);
        ini_set('smtp_port', EMAIL_SMTP_PORT);
        ini_set('sendmail_from', EMAIL_FROM_ADDRESS);
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>';
    $headers[] = 'Reply-To: ' . EMAIL_FROM_ADDRESS;

    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

function send_template_email($template, $recipientEmail, $data = []) {
    $templateData = get_email_template($template, $data);
    return send_email($recipientEmail, $templateData['subject'], $templateData['body']);
}
