<?php
require_once 'auth_guard.php';
require_once '../config/provisioner.php';
require_once '../config/email_helper.php';

$flash = $_SESSION['flash_finance'] ?? null;
unset($_SESSION['flash_finance']);

// ── HANDLE VERIFY / REJECT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $catatan  = trim($_POST['catatan'] ?? '');

    $stmt = $pdo_global->prepare("SELECT * FROM orders WHERE id=?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order && $order['status'] === 'pending') {
        if ($action === 'terima') {
            // ... existing terima logic ...
            // (unchanged)
            if ($order['catatan'] === 'RENEWAL' && $order['tenant_id']) {
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
                $pdo_global->prepare("UPDATE tenants SET status='aktif', tanggal_expire=?, package_id=?, alasan_nonaktif=NULL WHERE id=?")
                           ->execute([$new_expire, $order['package_id'], $order['tenant_id']]);
                $pdo_global->prepare("UPDATE orders SET status='diterima', tenant_id=? WHERE id=?")
                           ->execute([$order['tenant_id'], $order_id]);
                $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,?)")
                           ->execute([$order['tenant_id'], $t_info['status'] ?? 'expired', 'aktif', 'Renewal pembayaran dikonfirmasi', $_SESSION['superadmin_id']]);
                $_SESSION['flash_finance'] = ['type'=>'success','msg'=>"✅ Renewal #$order_id berhasil! Masa aktif diperpanjang hingga $new_expire.",'detail'=>''];
            } else {
            $result = provisionTenant($order_id, $pdo_global);
            if ($result['success']) {
                $_SESSION['flash_finance'] = [
                    'type' => 'success',
                    'msg'  => "✅ Order #{$order_id} diterima! Tenant <strong>{$result['subdomain']}</strong> berhasil diaktifkan.",
                    'detail' => "URL: <code>tenants/{$result['subdomain']}/</code> · Admin pass: <code>{$result['admin_pass']}</code>"
                ];
                $pkg_info = $pdo_global->prepare("SELECT nama FROM packages WHERE id=?");
                $pkg_info->execute([$order['package_id']]);
                $pkg_info = $pkg_info->fetch();
                $base_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/').'/';
                emailPlatformAktif([
                    'email'        => $order['email'],
                    'nama_pemilik' => $order['nama_pemilik'],
                    'nama_lembaga' => $order['nama_lembaga'],
                    'url'          => $base_url.'tenants/'.$result['subdomain'].'/',
                    'admin_pass'   => $result['admin_pass'],
                    'paket_nama'   => $pkg_info['nama'] ?? '',
                    'expire'       => date('d M Y', strtotime('+1 month')),
                ]);
            } else {
                $_SESSION['flash_finance'] = ['type'=>'danger','msg'=>'Gagal provisioning: '.$result['message']];
            }
            }

        } elseif ($action === 'tolak') {
            $pdo_global->prepare("UPDATE orders SET status='ditolak', catatan=? WHERE id=?")
                       ->execute([$catatan ?: 'Pembayaran ditolak oleh admin.', $order_id]);
            $_SESSION['flash_finance'] = ['type'=>'warning','msg'=>"Order #{$order_id} ditolak."];
        }
    }

    // Hapus order — berlaku untuk semua status
    if ($action === 'hapus_order' && $order) {
        // Hapus file bukti bayar jika ada
        if (!empty($order['bukti_bayar'])) {
            $file = dirname(__DIR__) . '/uploads/payments/' . basename($order['bukti_bayar']);
            if (file_exists($file)) @unlink($file);
        }
        $pdo_global->prepare("DELETE FROM orders WHERE id=?")->execute([$order_id]);
        $_SESSION['flash_finance'] = ['type'=>'danger','msg'=>"Order #{$order_id} (".htmlspecialchars($order['nama_lembaga'] ?? '').') berhasil dihapus.'];
    }

    header('Location: finance.php?status=' . urlencode($_GET['status'] ?? 'pending')); exit;
}

// ── LOAD ORDERS ──
$filter  = $_GET['status'] ?? 'pending';
$highlight = (int)($_GET['highlight'] ?? 0);
$where_status = $filter !== 'semua' ? "WHERE o.status = '{$filter}'" : "";

$orders = $pdo_global->query("
    SELECT o.*, p.nama as paket_nama, p.harga as paket_harga
    FROM orders o LEFT JOIN packages p ON o.package_id=p.id
    {$where_status}
    ORDER BY o.created_at DESC
")->fetchAll();

$counts = $pdo_global->query("SELECT status, COUNT(*) as n FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Verifikasi Pembayaran</div>
        <div class="sa-topbar-actions">
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">

        <?php if ($flash): ?>
        <div class="sa-alert sa-alert-<?= $flash['type'] ?>">
            <?= $flash['msg'] ?>
            <?php if (!empty($flash['detail'])): ?>
            <br><small><?= $flash['detail'] ?></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <?php foreach (['pending'=>'Pending','diterima'=>'Diterima','ditolak'=>'Ditolak','semua'=>'Semua'] as $k => $label): ?>
            <a href="?status=<?= $k ?>" class="btn-sa-outline <?= $filter===$k?'active':'' ?>"
               style="<?= $filter===$k?'background:var(--orange-dim);border-color:var(--orange);color:var(--orange)':'' ?>">
                <?= $label ?>
                <?php $n = $k==='semua' ? array_sum($counts) : ($counts[$k]??0); if($n>0): ?>
                <span style="background:<?= $k==='pending'?'var(--orange)':'rgba(255,255,255,.1)' ?>;color:#fff;border-radius:20px;padding:1px 7px;font-size:.7rem;margin-left:4px"><?= $n ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Orders -->
        <?php if (empty($orders)): ?>
        <div class="sa-card">
            <div class="empty-state" style="padding:4rem">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <div>Tidak ada order <?= $filter !== 'semua' ? '"'.$filter.'"' : '' ?></div>
            </div>
        </div>
        <?php else: foreach ($orders as $o):
            $is_highlight = $o['id'] === $highlight;
        ?>
        <div class="sa-card mb-3" id="order-<?= $o['id'] ?>" style="<?= $is_highlight ? 'border-color:var(--orange);box-shadow:var(--orange-glow)' : '' ?>">
            <div class="sa-card-header">
                <div class="d-flex align-items-center gap-2">
                    <span style="color:var(--text-muted);font-size:.85rem">#<?= $o['id'] ?></span>
                    <strong><?= htmlspecialchars($o['nama_lembaga']) ?></strong>
                    <span class="sa-badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
                    <?php if ($is_highlight): ?><span class="sa-badge" style="background:rgba(255,106,0,.15);color:var(--orange);border:1px solid rgba(255,106,0,.3)">⚡ Perlu Aksi</span><?php endif; ?>
                </div>
                <span style="font-size:.8rem;color:var(--text-muted)"><?= date('d M Y H:i', strtotime($o['created_at'])) ?></span>
            </div>
            <div class="sa-card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Data Pembeli</div>
                        <table style="width:100%;font-size:.88rem">
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0;width:130px">Nama Pemilik</td><td style="color:var(--text)"><?= htmlspecialchars($o['nama_pemilik']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0">Email</td><td style="color:var(--text)"><?= htmlspecialchars($o['email']) ?></td></tr>
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0">No. Telp</td><td style="color:var(--text)"><?= htmlspecialchars($o['no_telp'] ?? '—') ?></td></tr>
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0">Subdomain</td><td><code style="color:var(--cyan)"><?= htmlspecialchars($o['subdomain_request'] ?? '—') ?></code></td></tr>
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0">Paket</td><td style="color:var(--orange);font-weight:600"><?= htmlspecialchars($o['paket_nama'] ?? '—') ?></td></tr>
                            <tr><td style="color:var(--text-muted);padding:.2rem .5rem .2rem 0">Tagihan</td><td style="color:#fff;font-weight:700">Rp <?= number_format($o['harga_bayar'], 0, ',', '.') ?></td></tr>
                        </table>
                    </div>

                    <div class="col-md-4">
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Bukti Pembayaran</div>
                        <?php if ($o['bukti_bayar']): ?>
                        <a href="../uploads/payments/<?= htmlspecialchars($o['bukti_bayar']) ?>" target="_blank" style="display:block">
                            <img src="../uploads/payments/<?= htmlspecialchars($o['bukti_bayar']) ?>"
                                 style="width:100%;max-width:200px;border-radius:8px;border:1px solid var(--border);object-fit:cover"
                                 alt="Bukti Bayar" onerror="this.style.display='none'">
                        </a>
                        <a href="../uploads/payments/<?= htmlspecialchars($o['bukti_bayar']) ?>" target="_blank" class="btn-sa-outline mt-2" style="font-size:.78rem;padding:.3rem .7rem;display:inline-flex">
                            Buka Gambar ↗
                        </a>
                        <?php else: ?>
                        <div style="color:var(--text-muted);font-size:.85rem">Belum ada bukti bayar diunggah.</div>
                        <?php endif; ?>
                        <?php if ($o['catatan']): ?>
                        <div style="margin-top:.75rem;padding:.6rem .9rem;background:var(--navy);border-radius:8px;font-size:.82rem;color:var(--text-sub)">
                            <strong>Catatan:</strong> <?= htmlspecialchars($o['catatan']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($o['status'] === 'pending'): ?>
                    <div class="col-md-3">
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Aksi Verifikasi</div>
                        <!-- Terima -->
                        <form method="POST" onsubmit="return confirm('Terima pembayaran ini dan aktifkan tenant?')">
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <input type="hidden" name="action" value="terima">
                            <button type="submit" class="btn-sa-success w-100 mb-2" style="padding:.6rem;justify-content:center">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Terima &amp; Aktifkan Tenant
                            </button>
                        </form>
                        <!-- Tolak -->
                        <button onclick="showTolakModal(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['nama_lembaga'])) ?>')" class="btn-sa-danger w-100 mb-2" style="padding:.6rem;justify-content:center">
                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Tolak Pembayaran
                        </button>
                        <!-- Hapus Order -->
                        <button onclick="showHapusOrderModal(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['nama_lembaga'])) ?>')" class="btn-sa-outline w-100" style="padding:.6rem;justify-content:center;border-color:#EF4444;color:#EF4444">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Hapus Order
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- Tombol Hapus untuk order non-pending (diterima/ditolak) -->
                    <div class="col-md-3">
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Aksi</div>
                        <button onclick="showHapusOrderModal(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['nama_lembaga'])) ?>')" class="btn-sa-outline w-100" style="padding:.6rem;justify-content:center;border-color:#EF4444;color:#EF4444">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Hapus Order
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>

    </div>
</div>

<!-- Modal Tolak -->
<div class="sa-modal-backdrop" id="modalTolak">
    <div class="sa-modal">
        <div class="sa-modal-title">Tolak Pembayaran</div>
        <p style="color:var(--text-sub);font-size:.9rem;margin-bottom:1.2rem">
            Tolak order dari: <strong id="modalNamaOrder" style="color:#fff"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="tolak">
            <input type="hidden" name="order_id" id="modalOrderId">
            <div class="sa-form-group">
                <label>Alasan Penolakan <span style="color:var(--orange)">*</span></label>
                <textarea name="catatan" class="sa-form-control" rows="3" required placeholder="Contoh: Bukti pembayaran tidak valid / nominal tidak sesuai..."></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" onclick="closeModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-danger" style="padding:.5rem 1.2rem">Tolak Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Order -->
<div class="sa-modal-backdrop" id="modalHapusOrder">
    <div class="sa-modal" style="max-width:420px">
        <div class="sa-modal-title" style="color:#EF4444">🗑️ Hapus Order</div>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:1rem;margin-bottom:1.2rem;font-size:.875rem">
            <strong style="color:#EF4444">⚠️ Order akan dihapus permanen!</strong><br>
            <span style="color:var(--text-sub)">Order dari: <strong id="hapusOrderNama" style="color:#fff"></strong></span><br>
            <span style="color:var(--text-muted);font-size:.8rem">Bukti pembayaran juga akan ikut terhapus dari server.</span>
        </div>
        <form method="POST" id="formHapusOrder">
            <input type="hidden" name="action" value="hapus_order">
            <input type="hidden" name="order_id" id="hapusOrderId">
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" onclick="closeHapusOrderModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-danger" style="padding:.5rem 1.2rem">
                    🗑️ Ya, Hapus Order
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTolakModal(id, nama) {
    document.getElementById('modalOrderId').value = id;
    document.getElementById('modalNamaOrder').textContent = nama;
    document.getElementById('modalTolak').classList.add('show');
}
function closeModal() {
    document.getElementById('modalTolak').classList.remove('show');
}
document.getElementById('modalTolak').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
function showHapusOrderModal(id, nama) {
    document.getElementById('hapusOrderId').value = id;
    document.getElementById('hapusOrderNama').textContent = nama;
    document.getElementById('modalHapusOrder').classList.add('show');
}
function closeHapusOrderModal() {
    document.getElementById('modalHapusOrder').classList.remove('show');
}
document.getElementById('modalHapusOrder').addEventListener('click', function(e) {
    if (e.target === this) closeHapusOrderModal();
});
// Scroll ke highlight
<?php if ($highlight): ?>
setTimeout(() => {
    const el = document.getElementById('order-<?= $highlight ?>');
    if (el) el.scrollIntoView({ behavior:'smooth', block:'center' });
}, 300);
<?php endif; ?>
</script>
</body>
</html>
