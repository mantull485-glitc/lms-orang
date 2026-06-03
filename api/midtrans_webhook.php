<?php
// ============================================================
// MIDTRANS NOTIFICATION / WEBHOOK HANDLER
// URL: https://yourdomain.com/api/midtrans_webhook.php
// Daftarkan URL ini di Midtrans Dashboard:
//   Setting > Configuration > Payment Notification URL
// ============================================================
require_once dirname(__DIR__) . '/config/superadmin_db.php';
require_once dirname(__DIR__) . '/config/midtrans.php';

header('Content-Type: application/json');

// Ambil raw JSON dari Midtrans
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (empty($data['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// ---- Verifikasi signature key ----
// signature_key = SHA512(order_id + status_code + gross_amount + server_key)
$expected_sig = hash('sha512',
    $data['order_id'] .
    ($data['status_code'] ?? '') .
    ($data['gross_amount'] ?? '') .
    MIDTRANS_SERVER_KEY
);

if ($expected_sig !== ($data['signature_key'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$mt_order_id       = $data['order_id'];        // e.g. PLK-1717000000-123
$transaction_status = $data['transaction_status'] ?? '';
$fraud_status       = $data['fraud_status'] ?? '';
$payment_type       = $data['payment_type'] ?? 'midtrans';

// ---- Peta status Midtrans → status order lokal ----
$new_status = match(true) {
    $transaction_status === 'capture' && $fraud_status === 'accept'   => 'diterima',
    $transaction_status === 'settlement'                               => 'diterima',
    $transaction_status === 'cancel'                                   => 'ditolak',
    $transaction_status === 'deny'                                     => 'ditolak',
    $transaction_status === 'expire'                                   => 'ditolak',
    $transaction_status === 'pending'                                  => 'pending',
    default                                                            => null,
};

if ($new_status === null) {
    echo json_encode(['message' => 'Status ignored: ' . $transaction_status]);
    exit;
}

// ---- Update order di database ----
try {
    $stmt = $pdo_global->prepare("
        UPDATE orders
        SET status        = ?,
            metode_bayar  = ?,
            updated_at    = NOW()
        WHERE midtrans_order_id = ?
    ");
    $stmt->execute([$new_status, $payment_type, $mt_order_id]);

    // Jika diterima, bisa trigger aktivasi tenant di sini (opsional)
    if ($new_status === 'diterima') {
        // TODO: panggil fungsi aktivasi tenant jika ada
    }

    echo json_encode(['message' => 'OK', 'status' => $new_status]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
