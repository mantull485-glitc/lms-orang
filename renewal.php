<?php
// ============================================================
// HALAMAN PERPANJANG / RENEWAL PAKET
// Diakses oleh pemilik LPK yang ingin perpanjang
// ============================================================
session_start();
require_once 'config/superadmin_db.php';

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error = '';
$tenant = null;

// Cari tenant berdasarkan email
if ($email) {
    $stmt = $pdo_global->prepare("SELECT t.*, p.nama as paket_nama FROM tenants t LEFT JOIN packages p ON t.package_id=p.id WHERE t.email=?");
    $stmt->execute([$email]);
    $tenant = $stmt->fetch();
    if (!$tenant) $error = 'Email tidak ditemukan dalam sistem kami.';
}

// Handle submit renewal order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal']) && $tenant) {
    $paket_id = (int)$_POST['paket_id'];
    $billing  = $_POST['billing'] ?? 'bulanan';

    $pkg = $pdo_global->prepare("SELECT * FROM packages WHERE id=? AND status='aktif'");
    $pkg->execute([$paket_id]);
    $pkg = $pkg->fetch();

    if ($pkg) {
        $harga = $billing === 'tahunan' && $pkg['harga_tahunan'] ? $pkg['harga_tahunan'] : $pkg['harga'];
        $_SESSION['pending_order'] = [
            'nama_lembaga'  => $tenant['nama_lembaga'],
            'nama_pemilik'  => $tenant['nama_pemilik'],
            'email'         => $tenant['email'],
            'no_telp'       => $tenant['no_telp'] ?? '',
            'subdomain'     => $tenant['subdomain'],
            'paket_id'      => $paket_id,
            'paket_nama'    => $pkg['nama'],
            'billing'       => $billing,
            'harga'         => $harga,
            'is_renewal'    => true,
            'tenant_id'     => $tenant['id'],
        ];
        header('Location: renewal_payment.php'); exit;
    }
}

$packages = $pdo_global->query("SELECT * FROM packages WHERE status='aktif' ORDER BY harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perpanjang Paket – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
</head>
<body>
<nav class="sales-nav">
    <div class="container">
        <a class="nav-brand" href="index.php">Platform<span>.</span>LPK</a>
        <a href="index.php" class="btn-outline-sales" style="padding:.4rem 1rem;font-size:.85rem">← Beranda</a>
    </div>
</nav>

<div style="padding-top:5rem;min-height:100vh;background:var(--navy)">
<div class="container" style="max-width:680px;padding:3rem 1rem">

    <div style="text-align:center;margin-bottom:2.5rem">
        <div class="section-badge">Perpanjang Paket</div>
        <h2 class="section-title">Renewal Platform Anda</h2>
        <p style="color:var(--text-muted);font-size:.92rem">Masukkan email terdaftar untuk melihat informasi paket Anda.</p>
    </div>

    <!-- Email lookup -->
    <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem;margin-bottom:1.5rem">
        <form method="GET">
            <label class="sales-form-label">Email Akun Platform Anda</label>
            <div style="display:flex;gap:.75rem">
                <input type="email" name="email" class="sales-form-control" required placeholder="email@lembaga.com" value="<?= htmlspecialchars($email) ?>">
                <button type="submit" class="btn-primary-sales" style="white-space:nowrap">Cari Akun</button>
            </div>
            <?php if ($error): ?>
            <div style="color:#EF4444;font-size:.85rem;margin-top:.5rem">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($tenant): ?>
    <!-- Tenant info -->
    <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem">
        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem">Informasi Platform</div>
        <?php
        $status_color = match($tenant['status']) {
            'aktif'    => '#10B981',
            'nonaktif','expired' => '#EF4444',
            default    => '#F59E0B',
        };
        $rows = [
            ['Nama Lembaga', $tenant['nama_lembaga']],
            ['Paket Saat Ini', $tenant['paket_nama'] ?? '—'],
            ['Status', ucfirst($tenant['status'])],
            ['Aktif Hingga', $tenant['tanggal_expire'] ? date('d M Y', strtotime($tenant['tanggal_expire'])) : '—'],
        ];
        foreach ($rows as [$k,$v]):
        ?>
        <div style="display:flex;justify-content:space-between;padding:.3rem 0;font-size:.88rem;border-bottom:1px solid rgba(255,255,255,.04)">
            <span style="color:var(--text-muted)"><?= $k ?></span>
            <strong style="color:<?= $k==='Status'?$status_color:'#fff' ?>"><?= htmlspecialchars($v) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Renewal form -->
    <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem">
        <h5 style="color:#fff;font-weight:700;margin-bottom:1.25rem">Pilih Paket Perpanjangan</h5>
        <form method="POST">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <div class="row g-2 mb-3">
                <?php foreach ($packages as $p): ?>
                <div class="col-4">
                    <label style="display:block;cursor:pointer">
                        <input type="radio" name="paket_id" value="<?= $p['id'] ?>" style="display:none" class="pkg-radio" <?= $p['id']==($tenant['package_id']??0)?'checked':'' ?>>
                        <div class="pkg-opt" style="border:1px solid var(--border);border-radius:8px;padding:.75rem;text-align:center;transition:all .2s">
                            <div style="font-weight:700;color:#fff;font-size:.88rem"><?= htmlspecialchars($p['nama']) ?></div>
                            <div style="font-size:.72rem;color:var(--orange)">Rp <?= number_format($p['harga'],0,',','.') ?>/bln</div>
                            <?php if ($p['id'] === ($tenant['package_id'] ?? 0)): ?>
                            <div style="font-size:.65rem;color:#10B981;margin-top:2px">Paket saat ini</div>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-bottom:1.2rem">
                <label class="sales-form-label">Siklus Pembayaran</label>
                <div style="display:flex;gap:1rem">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.88rem;color:var(--text-muted)">
                        <input type="radio" name="billing" value="bulanan" checked> Bulanan
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.88rem;color:var(--text-muted)">
                        <input type="radio" name="billing" value="tahunan"> Tahunan <span style="color:#10B981;font-size:.78rem">(hemat s.d 20%)</span>
                    </label>
                </div>
            </div>
            <button type="submit" name="submit_renewal" class="btn-primary-sales w-100" style="justify-content:center;padding:.85rem">
                Lanjut ke Pembayaran Renewal
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
document.querySelectorAll('.pkg-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.pkg-opt').forEach(o => {
            o.style.borderColor = 'var(--border)';
            o.style.background  = 'transparent';
        });
        r.closest('label').querySelector('.pkg-opt').style.borderColor = 'var(--orange)';
        r.closest('label').querySelector('.pkg-opt').style.background  = 'rgba(255,106,0,.08)';
    });
    if (r.checked) {
        r.closest('label').querySelector('.pkg-opt').style.borderColor = 'var(--orange)';
        r.closest('label').querySelector('.pkg-opt').style.background  = 'rgba(255,106,0,.08)';
    }
});
</script>
</body>
</html>
