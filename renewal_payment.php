<?php
session_start();
require_once 'config/superadmin_db.php';

if (empty($_SESSION['pending_order']) || empty($_SESSION['pending_order']['is_renewal'])) {
    header('Location: renewal.php'); exit;
}
$order = $_SESSION['pending_order'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bukti_path = null;
    if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
            $errors[] = 'Format file tidak didukung.';
        } elseif ($_FILES['bukti_bayar']['size'] > 3*1024*1024) {
            $errors[] = 'Ukuran file maks. 3 MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/payments/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = 'renewal_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $upload_dir . $filename);
            $bukti_path = $filename;
        }
    } else {
        $errors[] = 'Bukti pembayaran wajib diunggah.';
    }

    if (empty($errors)) {
        // Buat order baru dengan catatan renewal
        $stmt = $pdo_global->prepare("INSERT INTO orders
            (tenant_id, nama_lembaga, nama_pemilik, email, no_telp, subdomain_request, package_id, harga_bayar, metode_bayar, bukti_bayar, status, catatan)
            VALUES (?,?,?,?,?,?,?,?,?,?,'pending','RENEWAL')");
        $stmt->execute([
            $order['tenant_id'],
            $order['nama_lembaga'],
            $order['nama_pemilik'],
            $order['email'],
            $order['no_telp'] ?? '',
            $order['subdomain'],
            $order['paket_id'],
            $order['harga'],
            $_POST['metode_bayar'] ?? 'Transfer',
            $bukti_path,
        ]);
        $order_id = $pdo_global->lastInsertId();
        $_SESSION['order_success_id'] = $order_id;
        unset($_SESSION['pending_order']);
        header('Location: success.php'); exit;
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
</head>
<body>
<nav class="sales-nav">
    <div class="container">
        <a class="nav-brand" href="index.php">Platform<span>.</span>LPK</a>
        <a href="renewal.php" class="btn-outline-sales" style="padding:.4rem 1rem;font-size:.85rem">← Kembali</a>
    </div>
</nav>

<div style="padding-top:5rem;min-height:100vh;background:var(--navy)">
<div class="container" style="max-width:800px;padding:3rem 1rem">

    <div style="text-align:center;margin-bottom:2rem">
        <div class="section-badge">Pembayaran Renewal</div>
        <h2 class="section-title" style="font-size:1.8rem">Perpanjang Platform Anda</h2>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem">
                <!-- Bank info -->
                <div style="background:rgba(255,106,0,.08);border:1px solid rgba(255,106,0,.2);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem">
                    <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem">Transfer ke Rekening</div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.9rem">
                        <span style="color:var(--text-muted)">Bank</span><strong style="color:#fff">BCA</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.9rem">
                        <span style="color:var(--text-muted)">No. Rekening</span>
                        <span>
                            <strong style="color:#fff" id="norek">1234567890</strong>
                            <button onclick="copyNorek()" style="background:rgba(255,255,255,.08);border:none;border-radius:4px;padding:1px 6px;color:var(--text-muted);font-size:.7rem;cursor:pointer;margin-left:6px" id="copy-btn">Salin</button>
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.9rem;border-top:1px solid rgba(255,106,0,.2);padding-top:.75rem;margin-top:.75rem">
                        <span style="color:var(--text-muted)">Total Transfer</span>
                        <strong style="color:var(--orange);font-size:1.1rem">Rp <?= number_format($order['harga'],0,',','.') ?></strong>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:.8rem 1rem;margin-bottom:1rem">
                    <?php foreach ($errors as $e): ?><div style="color:#EF4444;font-size:.85rem">• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom:1rem">
                        <label class="sales-form-label">Metode Pembayaran</label>
                        <select name="metode_bayar" class="sales-form-control">
                            <option>Transfer Bank BCA</option>
                            <option>Transfer Bank Mandiri</option>
                            <option>Transfer Bank BRI</option>
                            <option>QRIS</option>
                            <option>GoPay</option>
                        </select>
                    </div>
                    <div style="margin-bottom:1.5rem">
                        <label class="sales-form-label">Upload Bukti Pembayaran *</label>
                        <div style="border:2px dashed var(--border);border-radius:10px;padding:1.5rem;text-align:center;cursor:pointer" id="drop-zone" onclick="document.getElementById('bukti-input').click()">
                            <div style="font-size:1.8rem;margin-bottom:.4rem">📎</div>
                            <div style="color:var(--text-muted);font-size:.85rem">Klik untuk upload bukti bayar</div>
                            <div id="file-name" style="margin-top:.5rem;color:var(--orange);font-size:.82rem;display:none"></div>
                        </div>
                        <input type="file" id="bukti-input" name="bukti_bayar" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="showFN(this)">
                    </div>
                    <button type="submit" class="btn-primary-sales w-100" style="justify-content:center;padding:.85rem">Kirim Bukti Renewal</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:1.5rem">
                <div style="font-weight:700;color:#fff;margin-bottom:1rem">Ringkasan Renewal</div>
                <?php
                $rows = [
                    ['Lembaga', $order['nama_lembaga']],
                    ['Paket', $order['paket_nama']],
                    ['Siklus', ucfirst($order['billing'] ?? 'bulanan')],
                    ['Perpanjang', '1 ' . ($order['billing']==='tahunan'?'tahun':'bulan')],
                ];
                foreach ($rows as [$k,$v]):
                ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.88rem">
                    <span style="color:var(--text-muted)"><?= $k ?></span>
                    <strong style="color:#fff"><?= htmlspecialchars($v) ?></strong>
                </div>
                <?php endforeach; ?>
                <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:.5rem;display:flex;justify-content:space-between">
                    <span style="font-weight:700;color:#fff">Total</span>
                    <strong style="color:var(--orange);font-size:1.1rem">Rp <?= number_format($order['harga'],0,',','.') ?></strong>
                </div>
                <div style="margin-top:1rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:.75rem;font-size:.78rem;color:#10B981">
                    ✓ Verifikasi dalam 1×24 jam kerja<br>
                    ✓ Masa aktif diperpanjang otomatis
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function copyNorek() {
    navigator.clipboard.writeText(document.getElementById('norek').textContent);
    const b = document.getElementById('copy-btn');
    b.textContent = '✓'; b.style.color = '#10B981';
    setTimeout(() => { b.textContent = 'Salin'; b.style.color = ''; }, 2000);
}
function showFN(i) {
    const fn = document.getElementById('file-name');
    if (i.files[0]) { fn.textContent = '📎 ' + i.files[0].name; fn.style.display = 'block'; document.getElementById('drop-zone').style.borderColor = 'var(--orange)'; }
}
</script>
</body>
</html>
