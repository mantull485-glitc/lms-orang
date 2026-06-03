<?php
session_start();
require_once 'config/superadmin_db.php';

$order_id = $_SESSION['order_success_id'] ?? null;
if (!$order_id) { header('Location: index.php'); exit; }

$order_status = $_SESSION['order_status'] ?? 'pending';
unset($_SESSION['order_success_id'], $_SESSION['order_status']);

// Load order detail dari DB
$stmt = $pdo_global->prepare("SELECT o.*, p.nama as paket_nama FROM orders o LEFT JOIN packages p ON o.package_id=p.id WHERE o.id=?");
$stmt->execute([$order_id]);
$order_data = $stmt->fetch();

$is_paid    = ($order_status === 'diterima');
$is_pending = ($order_status === 'pending');
$is_failed  = ($order_status === 'ditolak');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pesanan – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--navy); }
        .success-card { background:var(--navy-light); border:1px solid var(--border); border-radius:24px; padding:3rem; max-width:540px; width:100%; text-align:center; }

        /* Icon circle */
        .status-circle {
            width:86px; height:86px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 1.5rem; animation:pop .5s ease;
        }
        .status-circle.paid    { background:rgba(16,185,129,.12); border:2px solid rgba(16,185,129,.3); }
        .status-circle.pending { background:rgba(245,158,11,.12); border:2px solid rgba(245,158,11,.3); }
        .status-circle.failed  { background:rgba(239,68,68,.12);  border:2px solid rgba(239,68,68,.3); }
        .status-circle svg { width:40px; height:40px; }
        @keyframes pop { 0%{transform:scale(0)} 60%{transform:scale(1.15)} 100%{transform:scale(1)} }

        .order-num { display:inline-block; padding:.4rem 1rem; border-radius:8px; font-weight:700; font-size:.9rem; margin-bottom:1.2rem; }
        .order-num.paid    { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.2); color:#10B981; }
        .order-num.pending { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.2); color:#F59E0B; }
        .order-num.failed  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.2);  color:#EF4444; }

        .info-list { background:rgba(255,255,255,.04); border-radius:12px; padding:1.2rem; text-align:left; margin:1.5rem 0; }
        .info-row  { display:flex; gap:8px; margin-bottom:.5rem; font-size:.88rem; }
        .info-row:last-child { margin-bottom:0; }

        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin:1.2rem 0; text-align:left; }
        .detail-item { background:rgba(255,255,255,.04); border-radius:8px; padding:.65rem .9rem; }
        .detail-label { font-size:.72rem; color:var(--text-muted); margin-bottom:.2rem; text-transform:uppercase; letter-spacing:.4px; }
        .detail-value { font-size:.88rem; color:#fff; font-weight:600; }
    </style>
</head>
<body>
<div style="padding:1rem;width:100%;display:flex;justify-content:center;align-items:center;min-height:100vh">
    <div class="success-card">

        <?php if ($is_paid): ?>
        <div class="status-circle paid">
            <svg fill="none" stroke="#10B981" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="order-num paid">Order #<?= htmlspecialchars($order_id) ?></div>
        <h2 style="color:#fff;font-weight:800;margin-bottom:.75rem">Pembayaran Berhasil! 🎉</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:0">
            Terima kasih! Pembayaran Anda telah dikonfirmasi oleh Midtrans. Platform Anda sedang dalam proses aktivasi.
        </p>
        <div class="info-list">
            <div class="info-row"><span style="color:#10B981">✓</span><span style="color:var(--text-muted)">Platform akan <strong style="color:#fff">aktif dalam 1×24 jam kerja</strong></span></div>
            <div class="info-row"><span style="color:#10B981">✓</span><span style="color:var(--text-muted)">Kredensial login dikirim ke <strong style="color:#fff"><?= htmlspecialchars($order_data['email'] ?? $order_id) ?></strong></span></div>
            <div class="info-row"><span style="color:#10B981">✓</span><span style="color:var(--text-muted)">Simpan nomor order: <strong style="color:var(--orange)">#<?= htmlspecialchars($order_id) ?></strong></span></div>
        </div>

        <?php elseif ($is_pending): ?>
        <div class="status-circle pending">
            <svg fill="none" stroke="#F59E0B" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="order-num pending">Order #<?= htmlspecialchars($order_id) ?></div>
        <h2 style="color:#fff;font-weight:800;margin-bottom:.75rem">Menunggu Pembayaran ⏳</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:0">
            Pesanan Anda telah dibuat. Selesaikan pembayaran sesuai instruksi yang diberikan Midtrans — platform akan aktif otomatis setelah pembayaran terkonfirmasi.
        </p>
        <div class="info-list">
            <div class="info-row"><span style="color:#F59E0B">⏳</span><span style="color:var(--text-muted)">Selesaikan pembayaran sesegera mungkin agar tidak <strong style="color:#fff">kedaluwarsa</strong></span></div>
            <div class="info-row"><span style="color:#F59E0B">📧</span><span style="color:var(--text-muted)">Konfirmasi akan dikirim ke <strong style="color:#fff"><?= htmlspecialchars($order_data['email'] ?? '') ?></strong></span></div>
            <div class="info-row"><span style="color:#F59E0B">🔖</span><span style="color:var(--text-muted)">Nomor order: <strong style="color:var(--orange)">#<?= htmlspecialchars($order_id) ?></strong></span></div>
        </div>

        <?php else: /* failed */ ?>
        <div class="status-circle failed">
            <svg fill="none" stroke="#EF4444" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <div class="order-num failed">Pembayaran Gagal</div>
        <h2 style="color:#fff;font-weight:800;margin-bottom:.75rem">Pembayaran Gagal ❌</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:0">
            Pembayaran dibatalkan atau ditolak. Tidak ada biaya yang dikenakan. Silakan coba lagi.
        </p>
        <div class="info-list">
            <div class="info-row"><span style="color:#EF4444">✗</span><span style="color:var(--text-muted)">Tidak ada dana yang tertarik dari akun Anda</span></div>
            <div class="info-row"><span style="color:#EF4444">↩</span><span style="color:var(--text-muted)">Silakan kembali dan coba pembayaran ulang</span></div>
        </div>
        <?php endif; ?>

        <?php if ($order_data): ?>
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Paket</div>
                <div class="detail-value"><?= htmlspecialchars($order_data['paket_nama'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Total</div>
                <div class="detail-value" style="color:var(--orange)">Rp <?= number_format($order_data['harga_bayar'] ?? 0, 0, ',', '.') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Lembaga</div>
                <div class="detail-value"><?= htmlspecialchars($order_data['nama_lembaga'] ?? '—') ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Subdomain</div>
                <div class="detail-value" style="color:var(--cyan);font-size:.82rem"><?= htmlspecialchars($order_data['subdomain_request'] ?? '—') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:flex;flex-direction:column;gap:.75rem">
            <?php if ($is_failed): ?>
            <a href="payment.php" class="btn-primary-sales" style="display:block;text-align:center;padding:.75rem">🔄 Coba Lagi</a>
            <?php endif; ?>
            <a href="index.php" class="btn-outline-sales" style="display:block;text-align:center;padding:.75rem">Kembali ke Beranda</a>
        </div>

        <div style="margin-top:1.5rem;font-size:.78rem;color:var(--text-muted)">
            Pertanyaan? Hubungi kami di <a href="mailto:support@platform.com" style="color:var(--orange)">support@platform.com</a>
            atau WhatsApp <a href="https://wa.me/6281234567890" style="color:var(--orange)">0812-3456-7890</a>
        </div>
    </div>
</div>
</body>
</html>
