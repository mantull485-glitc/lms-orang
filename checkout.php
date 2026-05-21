<?php
require_once 'config/superadmin_db.php';
session_start();

// Load paket
$paket_id = (int)($_GET['paket'] ?? $_POST['paket_id'] ?? 0);
if ($paket_id) {
    $stmt = $pdo_global->prepare("SELECT * FROM packages WHERE id=? AND status='aktif'");
    $stmt->execute([$paket_id]);
    $paket = $stmt->fetch();
}
if (!isset($paket)) {
    $packages = $pdo_global->query("SELECT * FROM packages WHERE status='aktif' ORDER BY harga ASC")->fetchAll();
}

$errors = [];
$step   = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $nama_lembaga     = trim($_POST['nama_lembaga'] ?? '');
    $nama_pemilik     = trim($_POST['nama_pemilik'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $no_telp          = trim($_POST['no_telp'] ?? '');
    $subdomain_req    = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['subdomain'] ?? '')));
    $paket_id_post    = (int)($_POST['paket_id'] ?? 0);
    $billing          = $_POST['billing'] ?? 'bulanan';

    // Validasi
    if (empty($nama_lembaga)) $errors[] = 'Nama lembaga wajib diisi.';
    if (empty($nama_pemilik)) $errors[] = 'Nama pemilik wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if (empty($subdomain_req)) $errors[] = 'Subdomain wajib diisi (hanya huruf kecil, angka, underscore).';
    if (strlen($subdomain_req) < 3) $errors[] = 'Subdomain minimal 3 karakter.';

    // Cek email & subdomain unik
    $chk_email = $pdo_global->prepare("SELECT id FROM orders WHERE email=? AND status!='ditolak'");
    $chk_email->execute([$email]);
    if ($chk_email->fetch()) $errors[] = 'Email ini sudah pernah melakukan pemesanan.';

    $chk_sub = $pdo_global->prepare("SELECT id FROM tenants WHERE subdomain=?");
    $chk_sub->execute([$subdomain_req]);
    if ($chk_sub->fetch()) $errors[] = 'Subdomain "'.$subdomain_req.'" sudah dipakai, pilih yang lain.';

    $chk_sub2 = $pdo_global->prepare("SELECT id FROM orders WHERE subdomain_request=? AND status!='ditolak'");
    $chk_sub2->execute([$subdomain_req]);
    if ($chk_sub2->fetch()) $errors[] = 'Subdomain ini sedang dalam proses order lain.';

    // Load paket
    $pkg_stmt = $pdo_global->prepare("SELECT * FROM packages WHERE id=? AND status='aktif'");
    $pkg_stmt->execute([$paket_id_post]);
    $paket_order = $pkg_stmt->fetch();
    if (!$paket_order) $errors[] = 'Paket tidak valid.';

    if (empty($errors) && $paket_order) {
        $harga = $billing === 'tahunan' && $paket_order['harga_tahunan']
            ? $paket_order['harga_tahunan']
            : $paket_order['harga'];

        // Simpan ke session untuk halaman payment
        $_SESSION['pending_order'] = [
            'nama_lembaga'  => $nama_lembaga,
            'nama_pemilik'  => $nama_pemilik,
            'email'         => $email,
            'no_telp'       => $no_telp,
            'subdomain'     => $subdomain_req,
            'paket_id'      => $paket_id_post,
            'paket_nama'    => $paket_order['nama'],
            'billing'       => $billing,
            'harga'         => $harga,
        ];
        header('Location: payment.php'); exit;
    }
}

$all_packages = $pdo_global->query("SELECT * FROM packages WHERE status='aktif' ORDER BY harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
</head>
<body>
<!-- NAVBAR -->
<nav class="sales-nav">
    <div class="container">
        <a class="nav-brand" href="index.php">Platform<span>.</span>LPK</a>
        <a href="index.php#harga" class="btn-outline-sales" style="padding:.4rem 1rem;font-size:.85rem">← Kembali</a>
    </div>
</nav>

<div style="padding-top:5rem;min-height:100vh;background:var(--navy)">
    <div class="container" style="max-width:900px;padding:3rem 1rem">

        <!-- Progress -->
        <div style="display:flex;align-items:center;gap:0;margin-bottom:3rem;justify-content:center">
            <?php foreach (['Pilih Paket','Data Lembaga','Pembayaran','Selesai'] as $i => $s): ?>
            <div style="display:flex;align-items:center">
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;background:<?= $i<=1?'var(--orange)':'rgba(255,255,255,.1)' ?>;color:<?= $i<=1?'#fff':'var(--text-muted)' ?>"><?= $i+1 ?></div>
                    <div style="font-size:.72rem;color:<?= $i<=1?'var(--orange)':'var(--text-muted)' ?>;white-space:nowrap"><?= $s ?></div>
                </div>
                <?php if ($i < 3): ?><div style="width:60px;height:1px;background:<?= $i<1?'var(--orange)':'rgba(255,255,255,.1)' ?>;margin:0 4px;margin-top:-20px"></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <!-- FORM -->
            <div class="col-lg-7">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem">
                    <h4 style="color:#fff;font-weight:700;margin-bottom:1.5rem">Data Lembaga</h4>

                    <?php if (!empty($errors)): ?>
                    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:1rem;margin-bottom:1.5rem">
                        <?php foreach ($errors as $e): ?>
                        <div style="color:#EF4444;font-size:.85rem;display:flex;gap:6px;align-items:flex-start;margin-bottom:.25rem">
                            <span>•</span><?= htmlspecialchars($e) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Pilih paket -->
                        <div style="margin-bottom:1.5rem">
                            <label class="sales-form-label">Paket yang Dipilih <span style="color:var(--orange)">*</span></label>
                            <div class="row g-2">
                                <?php foreach ($all_packages as $p): ?>
                                <div class="col-4">
                                    <label style="display:block;cursor:pointer">
                                        <input type="radio" name="paket_id" value="<?= $p['id'] ?>" style="display:none" class="pkg-radio" <?= ($paket_id===$p['id']||(!$paket_id&&$p['is_popular']))?'checked':'' ?>>
                                        <div class="pkg-opt" style="border:1px solid var(--border);border-radius:8px;padding:.75rem;text-align:center;transition:all .2s">
                                            <div style="font-weight:700;color:#fff;font-size:.9rem"><?= htmlspecialchars($p['nama']) ?></div>
                                            <div style="font-size:.72rem;color:var(--orange)">Rp <?= number_format($p['harga'],0,',','.') ?>/bln</div>
                                        </div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Billing cycle -->
                        <div style="margin-bottom:1.5rem">
                            <label class="sales-form-label">Siklus Pembayaran</label>
                            <div style="display:flex;gap:1rem">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.88rem;color:var(--text-muted)">
                                    <input type="radio" name="billing" value="bulanan" checked> Bulanan
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.88rem;color:var(--text-muted)">
                                    <input type="radio" name="billing" value="tahunan"> Tahunan <span style="color:#10B981;font-size:.78rem">(Hemat s.d 20%)</span>
                                </label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="sales-form-label">Nama Lembaga / LPK <span style="color:var(--orange)">*</span></label>
                                <input type="text" name="nama_lembaga" class="sales-form-control" required placeholder="contoh: LPK Maju Bersama" value="<?= htmlspecialchars($_POST['nama_lembaga'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="sales-form-label">Nama Pemilik <span style="color:var(--orange)">*</span></label>
                                <input type="text" name="nama_pemilik" class="sales-form-control" required placeholder="Nama lengkap" value="<?= htmlspecialchars($_POST['nama_pemilik'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="sales-form-label">No. WhatsApp</label>
                                <input type="tel" name="no_telp" class="sales-form-control" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_telp'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="sales-form-label">Email <span style="color:var(--orange)">*</span></label>
                                <input type="email" name="email" class="sales-form-control" required placeholder="email@lembaga.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="sales-form-label">Subdomain Platform <span style="color:var(--orange)">*</span></label>
                                <div style="position:relative">
                                    <input type="text" name="subdomain" id="subdomain" class="sales-form-control" required
                                           placeholder="lpkmajubersama" maxlength="30"
                                           pattern="[a-z0-9_]+"
                                           value="<?= htmlspecialchars($_POST['subdomain'] ?? preg_replace('/[^a-z0-9_]/', '', strtolower($paket['nama_lembaga'] ?? ''))) ?>"
                                           oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'')">
                                </div>
                                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.3rem">
                                    URL platform Anda: <code style="color:var(--cyan)" id="preview-url">domain.com/tenants/<span id="preview-sub">...</span>/</code>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="submit_order" class="btn-primary-sales w-100 mt-4" style="justify-content:center;padding:.85rem">
                            Lanjut ke Pembayaran
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ORDER SUMMARY -->
            <div class="col-lg-5">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:1.5rem;position:sticky;top:80px" id="order-summary">
                    <div style="font-weight:700;color:#fff;margin-bottom:1.2rem">Ringkasan Order</div>
                    <div id="summary-paket" style="color:var(--text-muted);font-size:.9rem">Pilih paket di sebelah kiri</div>
                    <div style="border-top:1px solid var(--border);margin-top:1.2rem;padding-top:1.2rem">
                        <div style="display:flex;justify-content:space-between;font-size:.88rem;color:var(--text-muted)">
                            <span>Subtotal</span>
                            <span id="summary-subtotal">—</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:800;color:#fff;margin-top:.5rem">
                            <span>Total</span>
                            <span style="color:var(--orange)" id="summary-total">—</span>
                        </div>
                    </div>
                    <div style="margin-top:1.2rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:.75rem;font-size:.78rem;color:#10B981">
                        ✓ Aktivasi dalam 1×24 jam kerja<br>
                        ✓ Support via WhatsApp & Email<br>
                        ✓ Garansi uang kembali 7 hari
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Package data from PHP
const pkgData = <?php
    $pkg_js = [];
    foreach ($all_packages as $p) {
        $pkg_js[$p['id']] = ['nama' => $p['nama'], 'harga' => $p['harga'], 'harga_tahunan' => $p['harga_tahunan']];
    }
    echo json_encode($pkg_js);
?>;

function updateSummary() {
    const selected = document.querySelector('.pkg-radio:checked');
    const billing  = document.querySelector('input[name="billing"]:checked')?.value;
    if (!selected) return;
    const pkg = pkgData[selected.value];
    if (!pkg) return;
    const harga = billing === 'tahunan' && pkg.harga_tahunan ? pkg.harga_tahunan : pkg.harga;
    const label = billing === 'tahunan' ? '/tahun' : '/bulan';
    document.getElementById('summary-paket').innerHTML = `<strong style="color:#fff">${pkg.nama}</strong><br><small style="color:var(--text-muted)">Siklus: ${billing==='tahunan'?'Tahunan':'Bulanan'}</small>`;
    const fmt = new Intl.NumberFormat('id-ID').format(harga);
    document.getElementById('summary-subtotal').textContent = `Rp ${fmt}${label}`;
    document.getElementById('summary-total').textContent = `Rp ${fmt}`;
}

// Style selected package
function stylePackages() {
    document.querySelectorAll('.pkg-radio').forEach(r => {
        const opt = r.closest('label').querySelector('.pkg-opt');
        opt.style.borderColor = r.checked ? 'var(--orange)' : 'var(--border)';
        opt.style.background  = r.checked ? 'rgba(255,106,0,.08)' : 'transparent';
    });
}

document.querySelectorAll('.pkg-radio, input[name="billing"]').forEach(el => {
    el.addEventListener('change', () => { updateSummary(); stylePackages(); });
});

// Subdomain preview
document.getElementById('subdomain').addEventListener('input', function() {
    document.getElementById('preview-sub').textContent = this.value || '...';
});

updateSummary(); stylePackages();
const sd = document.getElementById('subdomain');
document.getElementById('preview-sub').textContent = sd.value || '...';
</script>
</body>
</html>
