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
    // 1. Ambil data order sebelum di-update untuk mengetahui status lama & catatan
    $stmt_ord = $pdo_global->prepare("SELECT * FROM orders WHERE midtrans_order_id = ?");
    $stmt_ord->execute([$mt_order_id]);
    $order = $stmt_ord->fetch();

    if ($order) {
        // 2. Update status order ke status baru
        $stmt = $pdo_global->prepare("
            UPDATE orders
            SET status        = ?,
                metode_bayar  = ?,
                updated_at    = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $payment_type, $order['id']]);

        // 3. Jika status baru adalah diterima dan sebelumnya pending, jalankan aktivasi otomatis
        if ($new_status === 'diterima' && $order['status'] === 'pending') {
            require_once dirname(__DIR__) . '/config/provisioner.php';
            require_once dirname(__DIR__) . '/config/email_helper.php';

            if ($order['catatan'] === 'RENEWAL' && $order['tenant_id']) {
                // Logika renewal otomatis
                $billing_period = '+1 month';
                $pkg_r = $pdo_global->prepare("SELECT * FROM packages WHERE id=?");
                $pkg_r->execute([$order['package_id']]);
                $pkg_r = $pkg_r->fetch();
                if ($pkg_r && $pkg_r['harga_tahunan'] && $order['harga_bayar'] >= $pkg_r['harga_tahunan']) {
                    $billing_period = '+1 year';
                }
                
                $t_info = $pdo_global->prepare("SELECT * FROM tenants WHERE id=?");
                $t_info->execute([$order['tenant_id']]);
                $t_info = $t_info->fetch();
                
                $base_date = ($t_info && $t_info['tanggal_expire'] && strtotime($t_info['tanggal_expire']) > time())
                    ? $t_info['tanggal_expire'] : date('Y-m-d');
                $new_expire = date('Y-m-d', strtotime($base_date . ' ' . $billing_period));
                
                // Update expiry date tenant
                $pdo_global->prepare("UPDATE tenants SET status='aktif', tanggal_expire=?, package_id=?, alasan_nonaktif=NULL WHERE id=?")
                           ->execute([$new_expire, $order['package_id'], $order['tenant_id']]);
                           
                // Catat status log
                $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,NULL)")
                           ->execute([$order['tenant_id'], $t_info['status'] ?? 'expired', 'aktif', 'Renewal otomatis via Midtrans']);

                // Kirim email notifikasi perpanjangan berhasil
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';
                
                emailRenewalBerhasil([
                    'email'        => $order['email'],
                    'nama_pemilik' => $order['nama_pemilik'],
                    'nama_lembaga' => $order['nama_lembaga'],
                    'url'          => $base_url . 'tenants/' . ($t_info['subdomain'] ?? $order['subdomain_request']) . '/',
                    'expire'       => date('d M Y', strtotime($new_expire)),
                ]);
            } else {
                // Logika provisioning otomatis untuk tenant baru
                $result = provisionTenant($order['id'], $pdo_global);
                if ($result['success']) {
                    // Update metode bayar kembali karena provisionTenant meng-overwrite status & tenant_id
                    $pdo_global->prepare("UPDATE orders SET metode_bayar=?, updated_at=NOW() WHERE id=?")
                               ->execute([$payment_type, $order['id']]);

                    // Kirim email notifikasi platform aktif
                    $pkg_info = $pdo_global->prepare("SELECT nama FROM packages WHERE id=?");
                    $pkg_info->execute([$order['package_id']]);
                    $pkg_info = $pkg_info->fetch();
                    
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';
                    
                    emailPlatformAktif([
                        'email'        => $order['email'],
                        'nama_pemilik' => $order['nama_pemilik'],
                        'nama_lembaga' => $order['nama_lembaga'],
                        'url'          => $base_url . 'tenants/' . $result['subdomain'] . '/',
                        'admin_pass'   => $result['admin_pass'],
                        'paket_nama'   => $pkg_info['nama'] ?? '',
                        'expire'       => date('d M Y', strtotime('+1 month')),
                    ]);
                }
            }
        }
    }

    echo json_encode(['message' => 'OK', 'status' => $new_status]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
