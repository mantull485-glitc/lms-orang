<?php
session_start();
require_once 'config/superadmin_db.php';

// Pastikan ada pending order dari session
if (empty($_SESSION['pending_order'])) {
    header('Location: checkout.php'); exit;
}
$order = $_SESSION['pending_order'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload bukti bayar
    $bukti_path = null;
    if (isset($_FILES['bukti_bayar']) && $_FILES['bukti_bayar']['error'] === UPLOAD_ERR_OK) {
        $ext_ok = ['jpg','jpeg','png','pdf'];
        $ext    = strtolower(pathinfo($_FILES['bukti_bayar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_ok)) {
            $errors[] = 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.';
        } elseif ($_FILES['bukti_bayar']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 3 MB.';
        } else {
            $upload_dir = __DIR__ . '/uploads/payments/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
            $filename = 'order_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['bukti_bayar']['tmp_name'], $upload_dir . $filename)) {
                // Fallback: simpan sebagai base64 di database jika filesystem tidak tersedia (Vercel)
                $file_data = file_get_contents($_FILES['bukti_bayar']['tmp_name']);
                $bukti_path = 'data:' . $_FILES['bukti_bayar']['type'] . ';base64,' . base64_encode($file_data);
            } else {
                $bukti_path = $filename;
            }
        }
    } else {
        $errors[] = 'Bukti pembayaran wajib diunggah.';
    }

    if (empty($errors)) {
        try {
            // Simpan order ke database
            // Gunakan RETURNING id karena PostgreSQL tidak mendukung lastInsertId() tanpa nama sequence
            $stmt = $pdo_global->prepare("INSERT INTO orders 
                (nama_lembaga, nama_pemilik, email, no_telp, subdomain_request, package_id, harga_bayar, metode_bayar, bukti_bayar, status)
                VALUES (?,?,?,?,?,?,?,?,?,'pending')
                RETURNING id");
            $stmt->execute([
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
            $row = $stmt->fetch();
            $order_id = $row['id'] ?? null;

            if (!$order_id) {
                $errors[] = 'Gagal menyimpan order ke database. Silakan coba lagi.';
            } else {
                $_SESSION['order_success_id'] = $order_id;
                unset($_SESSION['pending_order']);
                header('Location: success.php'); exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}

// Load rekening info
$rek_info = [
    'bank' => 'BCA',
    'no'   => '1234567890',
    'atas_nama' => 'Platform LPK Indonesia',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran – Platform LPK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sales.css">
</head>
<body>
<nav class="sales-nav">
    <div class="container">
        <a class="nav-brand" href="index.php">Platform<span>.</span>LPK</a>
        <a href="checkout.php" class="btn-outline-sales" style="padding:.4rem 1rem;font-size:.85rem">← Kembali</a>
    </div>
</nav>

<div style="padding-top:5rem;min-height:100vh;background:var(--navy)">
    <div class="container" style="max-width:860px;padding:3rem 1rem">

        <!-- Progress -->
        <div style="display:flex;align-items:center;gap:0;margin-bottom:3rem;justify-content:center">
            <?php foreach (['Pilih Paket','Data Lembaga','Pembayaran','Selesai'] as $i => $s): ?>
            <div style="display:flex;align-items:center">
                <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;background:<?= $i<=2?'var(--orange)':'rgba(255,255,255,.1)' ?>;color:<?= $i<=2?'#fff':'var(--text-muted)' ?>"><?= $i+1 ?></div>
                    <div style="font-size:.72rem;color:<?= $i<=2?'var(--orange)':'var(--text-muted)' ?>;white-space:nowrap"><?= $s ?></div>
                </div>
                <?php if ($i < 3): ?><div style="width:60px;height:1px;background:<?= $i<2?'var(--orange)':'rgba(255,255,255,.1)' ?>;margin:0 4px;margin-top:-20px"></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:2rem">
                    <h4 style="color:#fff;font-weight:700;margin-bottom:.5rem">Instruksi Pembayaran</h4>
                    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1.5rem">Transfer ke rekening berikut kemudian upload bukti pembayaran.</p>

                    <!-- Bank Info -->
                    <div style="background:rgba(255,106,0,.08);border:1px solid rgba(255,106,0,.2);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem">
                        <div style="font-size:.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.75rem">Rekening Tujuan</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                            <span style="color:var(--text-muted);font-size:.88rem">Bank</span>
                            <strong style="color:#fff"><?= $rek_info['bank'] ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                            <span style="color:var(--text-muted);font-size:.88rem">No. Rekening</span>
                            <div style="display:flex;align-items:center;gap:8px">
                                <strong style="color:#fff;font-size:1.1rem" id="norek"><?= $rek_info['no'] ?></strong>
                                <button onclick="copyNorek()" style="background:rgba(255,255,255,.08);border:none;border-radius:5px;padding:2px 8px;color:var(--text-muted);font-size:.72rem;cursor:pointer" id="copy-btn">Salin</button>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                            <span style="color:var(--text-muted);font-size:.88rem">Atas Nama</span>
                            <strong style="color:#fff"><?= $rek_info['atas_nama'] ?></strong>
                        </div>
                        <div style="border-top:1px solid rgba(255,106,0,.2);padding-top:.75rem;margin-top:.75rem;display:flex;justify-content:space-between;align-items:center">
                            <span style="color:var(--text-muted);font-size:.88rem">Total Transfer</span>
                            <strong style="color:var(--orange);font-size:1.2rem">Rp <?= number_format($order['harga'], 0, ',', '.') ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:1rem;margin-bottom:1.5rem">
                        <?php foreach ($errors as $e): ?>
                        <div style="color:#EF4444;font-size:.85rem">• <?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div style="margin-bottom:1.2rem">
                            <label class="sales-form-label">Metode Pembayaran</label>
                            <select name="metode_bayar" class="sales-form-control">
                                <option>Transfer Bank BCA</option>
                                <option>Transfer Bank Mandiri</option>
                                <option>Transfer Bank BRI</option>
                                <option>Transfer Bank BNI</option>
                                <option>QRIS</option>
                                <option>GoPay</option>
                                <option>OVO</option>
                            </select>
                        </div>
                        <div style="margin-bottom:1.5rem">
                            <label class="sales-form-label">Upload Bukti Pembayaran <span style="color:var(--orange)">*</span></label>
                            <div id="drop-zone" style="border:2px dashed var(--border);border-radius:12px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s" onclick="document.getElementById('bukti-input').click()">
                                <div style="font-size:2rem;margin-bottom:.5rem">📎</div>
                                <div style="color:var(--text-muted);font-size:.88rem">Klik atau drag & drop file di sini</div>
                                <div style="color:#64748B;font-size:.78rem;margin-top:.25rem">JPG, PNG, PDF — Maks. 3 MB</div>
                                <div id="file-name" style="margin-top:.75rem;color:var(--orange);font-size:.85rem;display:none"></div>
                            </div>
                            <input type="file" id="bukti-input" name="bukti_bayar" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="showFileName(this)">
                        </div>
                        <button type="submit" class="btn-primary-sales w-100" style="justify-content:center;padding:.85rem">
                            Kirim Bukti Pembayaran
                        </button>
                        <div style="text-align:center;font-size:.78rem;color:var(--text-muted);margin-top:.75rem">
                            Pembayaran akan diverifikasi dalam 1×24 jam kerja
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-5">
                <div style="background:var(--navy-light);border:1px solid var(--border);border-radius:18px;padding:1.5rem">
                    <div style="font-weight:700;color:#fff;margin-bottom:1.2rem">Ringkasan Order</div>
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
                        <span style="color:var(--text-muted)">Subdomain</span>
                        <code style="color:var(--cyan)"><?= htmlspecialchars($order['subdomain']) ?></code>
                    </div>
                    <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem;display:flex;justify-content:space-between">
                        <span style="font-weight:700;color:#fff">Total</span>
                        <strong style="color:var(--orange);font-size:1.1rem">Rp <?= number_format($order['harga'], 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyNorek() {
    navigator.clipboard.writeText(document.getElementById('norek').textContent);
    const btn = document.getElementById('copy-btn');
    btn.textContent = 'Disalin!';
    btn.style.color = '#10B981';
    setTimeout(() => { btn.textContent = 'Salin'; btn.style.color = ''; }, 2000);
}
function showFileName(input) {
    const fn = document.getElementById('file-name');
    if (input.files[0]) {
        fn.textContent = '📎 ' + input.files[0].name;
        fn.style.display = 'block';
        document.getElementById('drop-zone').style.borderColor = 'var(--orange)';
    }
}
const dz = document.getElementById('drop-zone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = 'var(--orange)'; });
dz.addEventListener('dragleave', () => dz.style.borderColor = 'var(--border)');
dz.addEventListener('drop', e => {
    e.preventDefault();
    const inp = document.getElementById('bukti-input');
    inp.files = e.dataTransfer.files;
    showFileName(inp);
});
</script>
</body>
</html>
