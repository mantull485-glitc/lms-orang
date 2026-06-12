<?php
// ============================================================
// API: Buat Midtrans Snap Token
// POST /api/midtrans_token.php
// ============================================================
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/superadmin_db.php';
require_once dirname(__DIR__) . '/config/midtrans.php';

// Hanya boleh diakses dari sesi yang valid
if (empty($_SESSION['pending_order'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tidak ada pending order.']);
    exit;
}

$order = $_SESSION['pending_order'];

// Buat midtrans order_id unik
$mt_order_id = 'PLK-' . time() . '-' . rand(100, 999);
$_SESSION['midtrans_order_id'] = $mt_order_id;

$base_dir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . ($base_dir === '/' || $base_dir === '\\' ? '' : $base_dir) . '/';

$redirect_page = !empty($order['is_renewal']) ? 'renewal_payment.php' : 'payment.php';

$params = [
    'transaction_details' => [
        'order_id'     => $mt_order_id,
        'gross_amount' => (int)$order['harga'],
    ],
    'customer_details' => [
        'first_name' => $order['nama_pemilik'],
        'email'      => $order['email'],
        'phone'      => $order['no_telp'] ?? '',
    ],
    'item_details' => [
        [
            'id'       => 'PKG-' . ($order['paket_id'] ?? 0),
            'price'    => (int)$order['harga'],
            'quantity' => 1,
            'name'     => 'Platform LPK – Paket ' . ($order['paket_nama'] ?? 'Langganan'),
        ]
    ],
    'callbacks' => [
        'finish'  => $base_url . 'success.php',
        'error'   => $base_url . $redirect_page,
        'pending' => $base_url . $redirect_page,
    ],
];

$result = midtrans_create_snap_token($params);

if (isset($result['token'])) {
    // Pre-save order ke database dengan status pending
    // Sehingga webhook bisa menemukannya via midtrans_order_id
    try {
        $tenant_id  = !empty($order['is_renewal']) ? (int)$order['tenant_id'] : null;
        $catatan    = !empty($order['is_renewal']) ? 'RENEWAL' : null;

        $stmt = $pdo_global->prepare("
            INSERT INTO orders
                (tenant_id, nama_lembaga, nama_pemilik, email, no_telp, subdomain_request,
                 package_id, harga_bayar, metode_bayar, bukti_bayar,
                 midtrans_order_id, status, catatan)
            VALUES (?,?,?,?,?,?,?,?,NULL,NULL,?,'pending',?)
            ON CONFLICT (midtrans_order_id) DO NOTHING
            RETURNING id
        ");
        $stmt->execute([
            $tenant_id,
            $order['nama_lembaga'],
            $order['nama_pemilik'],
            $order['email'],
            $order['no_telp'] ?? '',
            $order['subdomain'],
            $order['paket_id'],
            $order['harga'],
            $mt_order_id,
            $catatan,
        ]);
        $row = $stmt->fetch();
        $_SESSION['db_order_id'] = $row['id'] ?? null;
    } catch (Exception $e) {
        // Jangan gagalkan token creation hanya karena DB error
        error_log('Midtrans pre-save error: ' . $e->getMessage());
    }

    echo json_encode(['token' => $result['token'], 'order_id' => $mt_order_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $result['error_messages'][0] ?? $result['error'] ?? 'Gagal membuat token Midtrans.']);
}
