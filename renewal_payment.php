<?php
session_start();
require_once 'config/superadmin_db.php';
require_once 'config/midtrans.php';

// Pastikan ada pending order dari session ATAU kita sedang memproses callback redirect
if ((empty($_SESSION['pending_order']) || empty($_SESSION['pending_order']['is_renewal'])) && empty($_GET['order_id'])) {
    header('Location: renewal.php'); exit;
}
$order = $_SESSION['pending_order'] ?? null;

// Handle callback dari Midtrans (redirect finish/pending/error)
if (!empty($_GET['order_id'])) {
    $mt_oid = $_GET['order_id'];
    // Cek status dari Midtrans API
    $status_data = midtrans_get_status($mt_oid);
    $txn_status  = $status_data['transaction_status'] ?? 'pending';
    $fraud       = $status_data['fraud_status']       ?? '';
    $payment_type = $status_data['payment_type']      ?? 'midtrans';

    $final_status = match(true) {
        ($txn_status === 'capture' && $fraud === 'accept') => 'diterima',
        $txn_status === 'settlement' => 'diterima',
        $txn_status === 'cancel'     => 'ditolak',
        $txn_status === 'deny'       => 'ditolak',
        $txn_status === 'expire'     => 'ditolak',
        default                      => 'pending',
    };

    // Cek apakah order sudah tersimpan (dari webhook atau pre-save)
    $existing = $pdo_global->prepare("SELECT id FROM orders WHERE midtrans_order_id = ?");
    $existing->execute([$mt_oid]);
    $existing_row = $existing->fetch();

    if ($existing_row) {
        // Order sudah ada, langsung redirect ke success
        $_SESSION['order_success_id'] = $existing_row['id'];
        $_SESSION['order_status']     = $final_status;
        
        // Update status jika berubah
        $pdo_global->prepare("UPDATE orders SET status=?, metode_bayar=?, updated_at=NOW() WHERE id=?")
            ->execute([$final_status, $payment_type, $existing_row['id']]);
        
        // Jika status sukses dan webhook belum memproses (karena status di db masih pending), kita perpanjang di sini untuk instan UX
        // Webhook juga akan melakukan ini (aman karena idempotent)
        if ($final_status === 'diterima') {
            try {
                // Ambil order detail
                $stmt_ord = $pdo_global->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt_ord->execute([$existing_row['id']]);
                $ord_det = $stmt_ord->fetch();
                
                if ($ord_det && $ord_det['status'] !== 'diterima') {
                    $billing_period = '+1 month';
                    $pkg_r = $pdo_global->prepare("SELECT * FROM packages WHERE id=?");
                    $pkg_r->execute([$ord_det['package_id']]);
                    $pkg_r = $pkg_r->fetch();
                    if ($pkg_r && $pkg_r['harga_tahunan'] && $ord_det['harga_bayar'] >= $pkg_r['harga_tahunan']) {
                        $billing_period = '+1 year';
                    }
                    
                    $t_info = $pdo_global->prepare("SELECT * FROM tenants WHERE id=?");
                    $t_info->execute([$ord_det['tenant_id']]);
                    $t_info = $t_info->fetch();
                    
                    $base_date = ($t_info && $t_info['tanggal_expire'] && strtotime($t_info['tanggal_expire']) > time())
                        ? $t_info['tanggal_expire'] : date('Y-m-d');
                    $new_expire = date('Y-m-d', strtotime($base_date . ' ' . $billing_period));
                    
                    // Update expiry date tenant
                    $pdo_global->prepare("UPDATE tenants SET status='aktif', tanggal_expire=?, package_id=?, alasan_nonaktif=NULL WHERE id=?")
                               ->execute([$new_expire, $ord_det['package_id'], $ord_det['tenant_id']]);
                               
                    // Catat status log
                    $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,NULL)")
                               ->execute([$ord_det['tenant_id'], $t_info['status'] ?? 'expired', 'aktif', 'Renewal otomatis via Midtrans Redirect']);
                }
            } catch (Exception $ex) {
                // Biarkan fail silently untuk UX redirect aman
            }
        }

        unset($_SESSION['pending_order'], $_SESSION['midtrans_order_id'], $_SESSION['db_order_id']);
        header('Location: success.php'); exit;
    } else {
        // Webhook belum sampai, simpan manual dari data redirect jika ada data order
        if ($final_status !== 'ditolak' && $order) {
            try {
                $stmt = $pdo_global->prepare("
                    INSERT INTO orders
                        (tenant_id, nama_lembaga, nama_pemilik, email, no_telp, subdomain_request,
                         package_id, harga_bayar, metode_bayar, bukti_bayar,
                         midtrans_order_id, status, catatan)
                    VALUES (?,?,?,?,?,?,?,?,NULL,?,'". $final_status ."', 'RENEWAL')
                    RETURNING id
                ");
                $stmt->execute([
                    $order['tenant_id'],
                    $order['nama_lembaga'],
                    $order['nama_pemilik'],
                    $order['email'],
                    $order['no_telp'] ?? '',
                    $order['subdomain'],
                    $order['paket_id'],
                    $order['harga'],
                    $payment_type,
                    $mt_oid,
                ]);
                $row = $stmt->fetch();
                
                // Perpanjang tenant jika status diterima
                if ($final_status === 'diterima') {
                    $billing_period = '+1 month';
                    $pkg_r = $pdo_global->prepare("SELECT * FROM packages WHERE id=?");
                    $pkg_r->execute([$order['paket_id']]);
                    $pkg_r = $pkg_r->fetch();
                    if ($pkg_r && $pkg_r['harga_tahunan'] && $order['harga'] >= $pkg_r['harga_tahunan']) {
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
                               ->execute([$new_expire, $order['paket_id'], $order['tenant_id']]);
                               
                    // Catat status log
                    $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,NULL)")
                               ->execute([$order['tenant_id'], $t_info['status'] ?? 'expired', 'aktif', 'Renewal otomatis via Midtrans Redirect']);
                }

                $_SESSION['order_success_id'] = $row['id'] ?? null;
                $_SESSION['order_status']     = $final_status;
                unset($_SESSION['pending_order'], $_SESSION['midtrans_order_id']);
                header('Location: success.php'); exit;
            } catch (Exception $e) {
                $payment_error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        } else {
            if ($final_status === 'ditolak') {
                $payment_error = 'Pembayaran dibatalkan / ditolak oleh Midtrans. Silakan coba lagi.';
            } else {
                header('Location: renewal.php'); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Renewal – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
    <script src="<?= MIDTRANS_SNAP_URL ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>
    <style>
        .pay-method-icon { width: 40px; height: 40px; object-fit: contain; }
        .midtrans-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(0,168,107,.1); border: 1px solid rgba(0,168,107,.3);
            border-radius: 8px; padding: .4rem .9rem; font-size: .78rem; color: #00a86b;
            margin-top: .75rem;
        }
        #pay-btn {
            position: relative; overflow: hidden;
            background: linear-gradient(135deg, #00a86b, #00c47e);
            color: #fff; border: none; border-radius: 12px;
            padding: .9rem; font-size: 1rem; font-weight: 700;
            width: 100%; cursor: pointer; transition: all .25s;
        }
        #pay-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,168,107,.4); }
        #pay-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        #pay-btn .spinner {
            display: none; width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .method-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
            margin-bottom: 1.5rem;
        }
        .method-item {
            background: rgba(255,255,255,.05); border: 1px solid var(--border);
            border-radius: 10px; padding: .75rem .5rem; text-align: center;
            font-size: .72rem; color: var(--text-muted);
        }
        .method-item span { font-size: 1.3rem; display: block; margin-bottom: .25rem; }
    </style>
</head>
<body>
<nav class="sales-nav">
    <div class="container">
        <a class="nav-brand" href="index.php">Platform<span>.</span>LPK</a>
        <a href="renewal.php" class="btn-outline-sales" style="padding:.4rem 1rem;font-size:.85rem">← Kembali</a>
    </div>
</nav>

<div style="padding-top:5rem;min-height:100vh;background:var(--navy)">
    <div class="container" style="max-width:860px;padding:3rem 1rem">

        <div style="text-align:center;margin-bottom:2.5rem">
            <div class="section-badge">Pembayaran Renewal</div>
            <h2 class="section-title" style="font-size:1.8rem">Perpanjang Platform Anda</h2>
        </div>

        <?php if (!empty($payment_error)): ?>
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:1rem;margin-bottom:1.5rem;color:#EF4444;font-size:.88rem">
            ⚠️ <?= htmlspecialchars($payment_error) ?>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem">
                    <h4 style="color:#fff;font-weight:700;margin-bottom:.4rem">Pembayaran</h4>
                    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1.75rem">
                        Pilih metode pembayaran melalui Midtrans — Transfer Bank, QRIS, E-wallet, dan lebih banyak lagi.
                    </p>

                    <!-- Midtrans Methods -->
                    <div class="method-grid">
                        <div class="method-item"><span>🏦</span>Transfer Bank</div>
                        <div class="method-item"><span>📱</span>QRIS</div>
                        <div class="method-item"><span>💚</span>GoPay</div>
                        <div class="method-item"><span>🟣</span>OVO</div>
                        <div class="method-item"><span>🔵</span>Dana</div>
                        <div class="method-item"><span>💳</span>Kartu Kredit</div>
                    </div>

                    <!-- Total -->
                    <?php if ($order): ?>
                    <div style="background:rgba(255,106,0,.08);border:1px solid rgba(255,106,0,.2);border-radius:12px;padding:1.25rem;margin-bottom:1.75rem">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <div>
                                <div style="color:var(--text-muted);font-size:.8rem;margin-bottom:.2rem">Total Pembayaran</div>
                                <div style="color:var(--orange);font-size:1.5rem;font-weight:800">
                                    Rp <?= number_format($order['harga'], 0, ',', '.') ?>
                                </div>
                                <div style="color:var(--text-muted);font-size:.78rem"><?= htmlspecialchars($order['paket_nama']) ?> · <?= ucfirst($order['billing'] ?? 'bulanan') ?></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:.78rem;color:var(--text-muted)">Untuk</div>
                                <div style="color:#fff;font-weight:600;font-size:.9rem"><?= htmlspecialchars($order['nama_lembaga']) ?></div>
                                <code style="color:var(--cyan);font-size:.78rem"><?= htmlspecialchars($order['subdomain']) ?></code>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Error container -->
                    <div id="snap-error" style="display:none;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:.9rem;margin-bottom:1.25rem;color:#EF4444;font-size:.85rem"></div>

                    <!-- Pay Button -->
                    <button id="pay-btn" onclick="startPayment()">
                        <span class="spinner" id="btn-spinner"></span>
                        <span id="btn-text">💳 &nbsp;Bayar Sekarang dengan Midtrans</span>
                    </button>

                    <div class="midtrans-badge">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Pembayaran diamankan oleh Midtrans · SSL 256-bit
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <?php if ($order): ?>
            <div class="col-lg-5">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:1.5rem;position:sticky;top:80px">
                    <div style="font-weight:700;color:#fff;margin-bottom:1.2rem">Ringkasan Renewal</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.88rem">
                        <span style="color:var(--text-muted)">Paket</span>
                        <strong style="color:#fff"><?= htmlspecialchars($order['paket_nama']) ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.88rem">
                        <span style="color:var(--text-muted)">Siklus</span>
                        <span style="color:var(--text)"><?= ucfirst($order['billing'] ?? 'bulanan') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.88rem">
                        <span style="color:var(--text-muted)">Lembaga</span>
                        <span style="color:var(--text)"><?= htmlspecialchars($order['nama_lembaga']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.88rem">
                        <span style="color:var(--text-muted)">Email</span>
                        <span style="color:var(--text);font-size:.8rem"><?= htmlspecialchars($order['email']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.6rem;font-size:.88rem">
                        <span style="color:var(--text-muted)">Subdomain</span>
                        <code style="color:var(--cyan)"><?= htmlspecialchars($order['subdomain']) ?></code>
                    </div>
                    <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem;display:flex;justify-content:space-between">
                        <span style="font-weight:700;color:#fff">Total</span>
                        <strong style="color:var(--orange);font-size:1.1rem">Rp <?= number_format($order['harga'], 0, ',', '.') ?></strong>
                    </div>

                    <div style="margin-top:1.2rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:.75rem;font-size:.78rem;color:#10B981">
                        ✓ Masa aktif diperpanjang otomatis setelah pembayaran<br>
                        ✓ Support via WhatsApp & Email<br>
                        ✓ Layanan tanpa downtime
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const ORDER_DATA = <?= json_encode($order ? [
    'harga'        => $order['harga'],
    'paket_nama'   => $order['paket_nama'],
    'nama_lembaga' => $order['nama_lembaga'],
] : []) ?>;

async function startPayment() {
    const btn     = document.getElementById('pay-btn');
    const spinner = document.getElementById('btn-spinner');
    const btnText = document.getElementById('btn-text');
    const errBox  = document.getElementById('snap-error');

    btn.disabled   = true;
    spinner.style.display = 'inline-block';
    btnText.textContent   = 'Memproses…';
    errBox.style.display  = 'none';

    try {
        const res  = await fetch('api/midtrans_token.php', { method: 'POST' });
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        const snapToken  = data.token;
        const mt_orderid = data.order_id;

        window.snap.pay(snapToken, {
            onSuccess: function(result) {
                window.location.href = 'renewal_payment.php?order_id=' + mt_orderid + '&result=success';
            },
            onPending: function(result) {
                window.location.href = 'renewal_payment.php?order_id=' + mt_orderid + '&result=pending';
            },
            onError: function(result) {
                showError('Pembayaran gagal. Silakan coba lagi atau hubungi support.');
            },
            onClose: function() {
                btn.disabled          = false;
                spinner.style.display = 'none';
                btnText.innerHTML     = '💳 &nbsp;Bayar Sekarang dengan Midtrans';
            }
        });
    } catch (e) {
        showError('Gagal memulai pembayaran: ' + e.message);
    }

    function showError(msg) {
        errBox.textContent    = '⚠️ ' + msg;
        errBox.style.display  = 'block';
        btn.disabled          = false;
        spinner.style.display = 'none';
        btnText.innerHTML     = '💳 &nbsp;Bayar Sekarang dengan Midtrans';
    }
}
</script>
</body>
</html>
