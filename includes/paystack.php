<?php
// Paystack helper functions

if (!defined('PAYSTACK_SECRET_KEY')) {
    $secretKey = getenv('PAYSTACK_SECRET_KEY') ?: '';
    define('PAYSTACK_SECRET_KEY', $secretKey);
}

if (!defined('PAYSTACK_PUBLIC_KEY')) {
    $publicKey = getenv('PAYSTACK_PUBLIC_KEY') ?: '';
    define('PAYSTACK_PUBLIC_KEY', $publicKey);
}

function paystackRequest($endpoint, $method = 'GET', $data = []) {
    $url = 'https://api.paystack.co' . $endpoint;
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => false, 'message' => $error, 'data' => null];
    }

    curl_close($ch);
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => false, 'message' => 'Unable to parse Paystack response.', 'data' => null];
    }

    if (!isset($decoded['status']) || !$decoded['status']) {
        $message = $decoded['message'] ?? 'Paystack API error';
        return ['status' => false, 'message' => $message, 'data' => $decoded];
    }

    return ['status' => true, 'data' => $decoded['data']];
}

function getPaystackCallbackUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host . '/varsity-vault/verify-payment.php';
}

function initializePayment($email, $amount, $metadata = []) {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    $amountKobo = (int) round($amount * 100);
    $payload = [
        'email' => $email,
        'amount' => $amountKobo,
        'currency' => 'ZAR',
        'callback_url' => getPaystackCallbackUrl(),
        'metadata' => $metadata
    ];

    return paystackRequest('/transaction/initialize', 'POST', $payload);
}

function verifyPayment($reference) {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    $reference = trim($reference);
    if (empty($reference)) {
        return ['status' => false, 'message' => 'Payment reference is required.'];
    }

    return paystackRequest('/transaction/verify/' . urlencode($reference), 'GET');
}

function createTransferRecipient($name, $accountNumber, $bankCode) {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    $payload = [
        'type' => 'nuban',
        'name' => $name,
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
        'currency' => 'ZAR'
    ];

    return paystackRequest('/transferrecipient', 'POST', $payload);
}

function initiateTransfer($recipientCode, $amount, $reason = 'Seller payout') {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    $amountKobo = (int) round($amount * 100);
    $payload = [
        'source' => 'balance',
        'amount' => $amountKobo,
        'recipient' => $recipientCode,
        'reason' => $reason
    ];

    return paystackRequest('/transfer', 'POST', $payload);
}

function getBankList() {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    return paystackRequest('/bank?currency=ZAR', 'GET');
}

function getPaystackBalance() {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    return paystackRequest('/balance', 'GET');
}
