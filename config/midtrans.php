<?php
// ============================================================
// KONFIGURASI MIDTRANS
// Key diambil dari tabel platform_config di Supabase
// Jalankan migration: INSERT INTO platform_config ...
// ============================================================

// Load local overrides untuk development (XAMPP) — tidak ada di production
$_local_env = __DIR__ . '/local_env.php';
if (file_exists($_local_env)) require_once $_local_env;
unset($_local_env);

// Pastikan koneksi DB tersedia
if (!isset($pdo_global)) require_once __DIR__ . '/superadmin_db.php';

// Ambil config dari database
function _midtrans_cfg(string $key, string $default = ''): string {
    global $pdo_global;
    // Cek env var dulu (untuk Vercel jika sudah di-set)
    $env_map = [
        'midtrans_server_key'    => 'MIDTRANS_SERVER_KEY_SBX',
        'midtrans_client_key'    => 'MIDTRANS_CLIENT_KEY_SBX',
        'midtrans_is_production' => '',
    ];
    if (!empty($env_map[$key])) {
        $env_val = getenv($env_map[$key]);
        if ($env_val) return $env_val;
    }
    // Fallback: baca dari tabel platform_config
    try {
        $stmt = $pdo_global->prepare("SELECT value FROM platform_config WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$_mt_is_prod  = _midtrans_cfg('midtrans_is_production', 'false') === 'true';

if (!defined('MIDTRANS_IS_PRODUCTION'))
    define('MIDTRANS_IS_PRODUCTION', $_mt_is_prod);

if (!defined('MIDTRANS_SERVER_KEY'))
    define('MIDTRANS_SERVER_KEY', $_mt_is_prod
        ? _midtrans_cfg('midtrans_server_key_prod')
        : _midtrans_cfg('midtrans_server_key'));

if (!defined('MIDTRANS_CLIENT_KEY'))
    define('MIDTRANS_CLIENT_KEY', $_mt_is_prod
        ? _midtrans_cfg('midtrans_client_key_prod')
        : _midtrans_cfg('midtrans_client_key'));

if (!defined('MIDTRANS_SNAP_URL'))
    define('MIDTRANS_SNAP_URL', MIDTRANS_IS_PRODUCTION
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js');

if (!defined('MIDTRANS_API_URL'))
    define('MIDTRANS_API_URL', MIDTRANS_IS_PRODUCTION
        ? 'https://api.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions');

if (!defined('MIDTRANS_STATUS_URL'))
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
