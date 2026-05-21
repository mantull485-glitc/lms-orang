<?php
session_start();
$order_id = $_SESSION['order_success_id'] ?? null;
if (!$order_id) { header('Location: index.php'); exit; }
unset($_SESSION['order_success_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--navy); }
        .success-card { background:var(--navy-light); border:1px solid var(--border); border-radius:24px; padding:3rem; max-width:520px; width:100%; text-align:center; }
        .check-circle { width:80px; height:80px; background:rgba(16,185,129,.12); border:2px solid rgba(16,185,129,.3); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; animation:pop .5s ease; }
        @keyframes pop { 0%{transform:scale(0)} 60%{transform:scale(1.15)} 100%{transform:scale(1)} }
        .check-circle svg { width:36px; height:36px; color:#10B981; }
        .order-num { background:rgba(255,106,0,.1); border:1px solid rgba(255,106,0,.2); border-radius:8px; display:inline-block; padding:.4rem 1rem; color:var(--orange); font-weight:700; font-size:.9rem; margin-bottom:1.2rem; }
        .info-list { background:rgba(255,255,255,.04); border-radius:12px; padding:1.2rem; text-align:left; margin:1.5rem 0; }
        .info-row { display:flex; gap:8px; margin-bottom:.5rem; font-size:.88rem; }
        .info-row:last-child { margin-bottom:0; }
        .info-icon { color:#10B981; flex-shrink:0; }
    </style>
</head>
<body>
<div style="padding:1rem;width:100%;display:flex;justify-content:center;align-items:center;min-height:100vh">
    <div class="success-card">
        <div class="check-circle">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="order-num">Order #<?= htmlspecialchars($order_id) ?></div>
        <h2 style="color:#fff;font-weight:800;margin-bottom:.75rem">Pesanan Diterima!</h2>
        <p style="color:var(--text-muted);line-height:1.7;margin-bottom:0">Terima kasih! Bukti pembayaran Anda telah kami terima dan sedang dalam proses verifikasi.</p>

        <div class="info-list">
            <div class="info-row">
                <span class="info-icon">✓</span>
                <span style="color:var(--text-muted)">Tim kami akan memverifikasi pembayaran dalam <strong style="color:#fff">1×24 jam kerja</strong></span>
            </div>
            <div class="info-row">
                <span class="info-icon">✓</span>
                <span style="color:var(--text-muted)">Setelah terverifikasi, platform Anda akan <strong style="color:#fff">langsung aktif</strong></span>
            </div>
            <div class="info-row">
                <span class="info-icon">✓</span>
                <span style="color:var(--text-muted)">Kredensial login akan dikirim ke <strong style="color:#fff">email Anda</strong></span>
            </div>
            <div class="info-row">
                <span class="info-icon">✓</span>
                <span style="color:var(--text-muted)">Nomor order Anda: <strong style="color:var(--orange)">#<?= htmlspecialchars($order_id) ?></strong> (simpan untuk referensi)</span>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:.75rem">
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
