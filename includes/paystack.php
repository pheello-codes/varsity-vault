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
    try {
        $url = 'https://api.paystack.co' . $endpoint;
        $headers = [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ];

        $ch = curl_init($url);
        if (!$ch) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Paystack API Error - $endpoint: $error");
            return ['status' => false, 'message' => $error, 'data' => null];
        }

        curl_close($ch);
        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Paystack JSON Error - $endpoint: " . json_last_error_msg());
            return ['status' => false, 'message' => 'Unable to parse Paystack response.', 'data' => null];
        }

        if (!isset($decoded['status']) || !$decoded['status']) {
            $message = $decoded['message'] ?? 'Paystack API error';
            error_log("Paystack API Error - $endpoint: $message (HTTP: $httpCode)");
            return ['status' => false, 'message' => $message, 'data' => $decoded];
        }

        return ['status' => true, 'data' => $decoded['data']];
    } catch (Exception $e) {
        error_log("Paystack function error - $endpoint: " . $e->getMessage());
        return ['status' => false, 'message' => 'An unexpected error occurred while processing the payment.', 'data' => null];
    }
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
        return ['status' => false, 'message' => 'Paystack secret key is not configured.', 'data' => getFallbackBankList()];
    }

    $result = paystackRequest('/bank?currency=ZAR', 'GET');
    
    // If API fails, provide fallback list
    if (!$result['status']) {
        $fallback = getFallbackBankList();
        if (!empty($fallback)) {
            return ['status' => true, 'message' => 'Using fallback bank list', 'data' => $fallback];
        }
    }
    
    return $result;
}

function getFallbackBankList() {
    // South African banks - fallback list for when API is unavailable
    return [
        ['name' => 'ABSA Bank Limited', 'code' => '632005'],
        ['name' => 'Standard Bank South Africa', 'code' => '051001'],
        ['name' => 'First National Bank (FNB)', 'code' => '250110'],
        ['name' => 'Nedbank Limited', 'code' => '198765'],
        ['name' => 'Capitec Bank', 'code' => '450105'],
        ['name' => 'Investec Bank Limited', 'code' => '100009'],
        ['name' => 'Bidvest Bank', 'code' => '462106'],
        ['name' => 'African Bank Limited', 'code' => '820160'],
        ['name' => 'HSBC Bank UK PLC', 'code' => '400171'],
        ['name' => 'Wesbank', 'code' => '655005'],
        ['name' => 'Bank of China Limited', 'code' => '304191'],
        ['name' => 'Ubank', 'code' => '632009'],
        ['name' => 'Grindrod Bank Limited', 'code' => '371447'],
        ['name' => 'RMB (Rand Merchant Bank)', 'code' => '282831'],
        ['name' => 'Discovery Bank', 'code' => '679002']
    ];
}

function getPaystackBalance() {
    if (empty(PAYSTACK_SECRET_KEY)) {
        return ['status' => false, 'message' => 'Paystack secret key is not configured.'];
    }

    return paystackRequest('/balance', 'GET');
}
