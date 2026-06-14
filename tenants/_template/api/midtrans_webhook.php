<?php
// ============================================================
// MIDTRANS NOTIFICATION / WEBHOOK HANDLER UNTUK TENANT
// URL: https://tenant-domain.com/api/midtrans_webhook.php
// Atau: https://platform-induk.com/tenants/subdomain/api/midtrans_webhook.php
// ============================================================
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$server_key = getSetting($pdo, 'midtrans_server_key', '', $tenant_id);

if (empty($server_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Midtrans is not configured']);
    exit;
}

// Verifikasi Signature
$expected_sig = hash('sha512',
    $data['order_id'] .
    ($data['status_code'] ?? '') .
    ($data['gross_amount'] ?? '') .
    $server_key
);

if ($expected_sig !== ($data['signature_key'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$mt_order_id = $data['order_id'];
$transaction_status = $data['transaction_status'] ?? '';
$fraud_status = $data['fraud_status'] ?? '';
$payment_type = $data['payment_type'] ?? 'midtrans';

// Peta status Midtrans -> status registrasi lokal
$new_status = match(true) {
    $transaction_status === 'capture' && $fraud_status === 'accept' => 'diterima',
    $transaction_status === 'settlement'                             => 'diterima',
    $transaction_status === 'cancel'                                 => 'ditolak',
    $transaction_status === 'deny'                                   => 'ditolak',
    $transaction_status === 'expire'                                 => 'ditolak',
    $transaction_status === 'pending'                                => 'pending',
    default                                                          => null,
};

if ($new_status === null) {
    echo json_encode(['message' => 'Status ignored']);
    exit;
}

// Update status registrasi kelas di database tenant
try {
    $stmt = $pdo->prepare("UPDATE registrations SET status = ?, metode_pembayaran = ? WHERE midtrans_order_id = ? AND tenant_id = ?");
    $stmt->execute([$new_status, $payment_type, $mt_order_id, $tenant_id]);
    
    echo json_encode(['message' => 'OK']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
