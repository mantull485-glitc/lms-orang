<?php
// ============================================================
// KONFIGURASI MIDTRANS
// ============================================================
// Ganti dengan key Anda dari https://dashboard.midtrans.com
// Sandbox: Setting > Access Keys
// Production: centang "Use Production Key" lalu ambil key-nya
// ============================================================

define('MIDTRANS_IS_PRODUCTION', false); // Ganti ke TRUE saat go-live

// Ambil dari environment variable (Vercel / .env lokal)
// JANGAN hardcode key di sini — gunakan env var!
define('MIDTRANS_SERVER_KEY_SANDBOX', getenv('MIDTRANS_SERVER_KEY_SBX') ?: '');
define('MIDTRANS_CLIENT_KEY_SANDBOX', getenv('MIDTRANS_CLIENT_KEY_SBX') ?: '');

// --- PRODUCTION KEYS ---
define('MIDTRANS_SERVER_KEY_PROD',   getenv('MIDTRANS_SERVER_KEY')     ?: 'Mid-server-XXXXXXXXXXXXXXXX');
define('MIDTRANS_CLIENT_KEY_PROD',   getenv('MIDTRANS_CLIENT_KEY')     ?: 'Mid-client-XXXXXXXXXXXXXXXX');

// ---- Computed (jangan diubah) ----
define('MIDTRANS_SERVER_KEY', MIDTRANS_IS_PRODUCTION ? MIDTRANS_SERVER_KEY_PROD : MIDTRANS_SERVER_KEY_SANDBOX);
define('MIDTRANS_CLIENT_KEY', MIDTRANS_IS_PRODUCTION ? MIDTRANS_CLIENT_KEY_PROD : MIDTRANS_CLIENT_KEY_SANDBOX);
define('MIDTRANS_SNAP_URL',   MIDTRANS_IS_PRODUCTION
    ? 'https://app.midtrans.com/snap/snap.js'
    : 'https://app.sandbox.midtrans.com/snap/snap.js');
define('MIDTRANS_API_URL',    MIDTRANS_IS_PRODUCTION
    ? 'https://api.midtrans.com/snap/v1/transactions'
    : 'https://app.sandbox.midtrans.com/snap/v1/transactions');
define('MIDTRANS_STATUS_URL', MIDTRANS_IS_PRODUCTION
    ? 'https://api.midtrans.com/v2'
    : 'https://api.sandbox.midtrans.com/v2');

/**
 * Buat Midtrans Snap Token
 */
function midtrans_create_snap_token(array $params): array {
    $payload = json_encode($params);
    $auth    = base64_encode(MIDTRANS_SERVER_KEY . ':');

    $ch = curl_init(MIDTRANS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => $err];
    return json_decode($response, true) ?? ['error' => 'Invalid response'];
}

/**
 * Cek status transaksi Midtrans
 */
function midtrans_get_status(string $order_id): array {
    $url  = MIDTRANS_STATUS_URL . '/' . urlencode($order_id) . '/status';
    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Basic ' . $auth,
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?? [];
}
