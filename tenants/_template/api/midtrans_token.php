<?php
session_start();
header('Content-Type: application/json');

require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';

if (empty($_SESSION['user_id']) || empty($_POST['class_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Permintaan tidak valid.']);
    exit;
}

$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$class_id = (int)$_POST['class_id'];
$user_id = $_SESSION['user_id'];

// Ambil detail kelas
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND tenant_id = ?");
$stmt->execute([$class_id, $tenant_id]);
$class = $stmt->fetch();

if (!$class) {
    http_response_code(404);
    echo json_encode(['error' => 'Kelas tidak ditemukan.']);
    exit;
}

$final_price = ($class['harga_spesial'] !== null) ? $class['harga_spesial'] : $class['harga'];

if ($final_price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Kelas ini gratis.']);
    exit;
}

// Ambil kunci API Midtrans milik Tenant
$server_key = getSetting($pdo, 'midtrans_server_key', '', $tenant_id);
$is_production = getSetting($pdo, 'midtrans_is_production', '0', $tenant_id) === '1';

if (empty($server_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Midtrans belum dikonfigurasi oleh admin lembaga.']);
    exit;
}

// Buat ID pesanan Midtrans
$mt_order_id = 'REG-' . $tenant_id . '-' . $user_id . '-' . $class_id . '-' . time();

$params = [
    'transaction_details' => [
        'order_id'     => $mt_order_id,
        'gross_amount' => (int)$final_price,
    ],
    'customer_details' => [
        'first_name' => $_SESSION['nama'] ?? 'User',
        'email'      => $_SESSION['email'] ?? '',
    ],
    'item_details' => [
        [
            'id'       => 'CLS-' . $class_id,
            'price'    => (int)$final_price,
            'quantity' => 1,
            'name'     => substr($class['nama_kelas'], 0, 50),
        ]
    ]
];

$url = $is_production ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

// Panggil API Snap Midtrans
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($server_key . ':')
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['token'])) {
    // Simpan data pendaftaran sebagai pending sebelum pop-up terbuka
    $stmt = $pdo->prepare("
        INSERT INTO registrations (tenant_id, user_id, class_id, status, harga_saat_daftar, midtrans_order_id, metode_pembayaran) 
        VALUES (?, ?, ?, 'pending', ?, ?, 'midtrans')
    ");
    $stmt->execute([$tenant_id, $user_id, $class_id, $final_price, $mt_order_id]);
    
    echo json_encode(['token' => $result['token'], 'order_id' => $mt_order_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $result['error_messages'][0] ?? 'Gagal menghubungi Midtrans.']);
}
