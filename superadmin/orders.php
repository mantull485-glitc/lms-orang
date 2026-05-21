<?php
require_once 'auth_guard.php';

$flash = $_SESSION['flash_orders'] ?? null;
unset($_SESSION['flash_orders']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'hapus_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $stmt = $pdo_global->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if ($order) {
            if (!empty($order['bukti_bayar'])) {
                $file = dirname(__DIR__) . '/uploads/payments/' . basename($order['bukti_bayar']);
                if (file_exists($file)) @unlink($file);
            }
            $pdo_global->prepare("DELETE FROM orders WHERE id=?")->execute([$order_id]);
            $_SESSION['flash_orders'] = ['type' => 'success', 'msg' => "Order #{$order_id} (" . htmlspecialchars($order['nama_lembaga']) . ") berhasil dihapus."];
        }
        header("Location: orders.php");
        exit;
    }
    
    if ($action === 'hapus_semua_order') {
        $konfirmasi = $_POST['konfirmasi'] ?? '';
        if ($konfirmasi === 'HAPUS SEMUA') {
            $all_orders = $pdo_global->query("SELECT bukti_bayar FROM orders")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($all_orders as $bukti) {
                if (!empty($bukti)) {
                    $file = dirname(__DIR__) . '/uploads/payments/' . basename($bukti);
                    if (file_exists($file)) @unlink($file);
                }
            }
            $pdo_global->exec("DELETE FROM orders");
            $_SESSION['flash_orders'] = ['type' => 'success', 'msg' => "Semua data order berhasil dihapus."];
        } else {
            $_SESSION['flash_orders'] = ['type' => 'danger', 'msg' => "Konfirmasi salah. Gagal menghapus semua data order."];
        }
        header("Location: orders.php");
        exit;
    }
}

$filter = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';
$where  = "WHERE 1=1";
$params = [];
if ($filter) { $where .= " AND o.status=?"; $params[] = $filter; }
if ($search) { $where .= " AND (o.nama_lembaga LIKE ? OR o.email LIKE ?)"; $params = array_merge($params, ["%$search%", "%$search%"]); }

$stmt = $pdo_global->prepare("SELECT o.*, p.nama as paket_nama FROM orders o LEFT JOIN packages p ON o.package_id=p.id $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = $pdo_global->query("SELECT status, COUNT(*) as n FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_revenue = $pdo_global->query("SELECT COALESCE(SUM(harga_bayar),0) FROM orders WHERE status='diterima'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Order – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Semua Order</div>
        <div class="sa-topbar-actions">
            <?php if (!empty($orders)): ?>
            <button onclick="showHapusSemuaModal()" class="btn-sa-danger" style="font-size:.82rem;padding:.45rem 1rem">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus Semua Order
            </button>
            <?php endif; ?>
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">
        <?php if ($flash): ?>
        <div class="sa-alert sa-alert-<?= $flash['type'] ?>">
            <?= $flash['msg'] ?>
        </div>
        <?php endif; ?>

        <!-- Summary row -->
        <div class="row g-3 mb-4">
            <?php
            $summary = [
                ['pending',  'Pending',  '#F59E0B', 'rgba(245,158,11,.1)'],
                ['diterima', 'Diterima', '#10B981', 'rgba(16,185,129,.1)'],
                ['ditolak',  'Ditolak',  '#EF4444', 'rgba(239,68,68,.1)'],
            ];
            foreach ($summary as [$k, $label, $color, $bg]):
            ?>
            <div class="col-sm-4">
                <div class="stat-card" style="cursor:pointer" onclick="location.href='orders.php?status=<?= $k ?>'">
                    <div class="stat-icon" style="background:<?= $bg ?>">
                        <svg fill="none" stroke="<?= $color ?>" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
                    </div>
                    <div>
                        <div class="stat-label"><?= $label ?></div>
                        <div class="stat-value" style="color:<?= $color ?>"><?= $counts[$k] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter -->
        <div class="sa-card mb-3">
            <div class="sa-card-body" style="padding:1rem 1.5rem">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="text" name="q" class="sa-form-control" style="width:240px" placeholder="Cari lembaga / email..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" class="sa-form-control" style="width:160px">
                        <option value="">Semua Status</option>
                        <option value="pending"  <?= $filter==='pending'?'selected':'' ?>>Pending</option>
                        <option value="diterima" <?= $filter==='diterima'?'selected':'' ?>>Diterima</option>
                        <option value="ditolak"  <?= $filter==='ditolak'?'selected':'' ?>>Ditolak</option>
                    </select>
                    <button type="submit" class="btn-sa-primary">Filter</button>
                    <?php if ($filter || $search): ?><a href="orders.php" class="btn-sa-outline">Reset</a><?php endif; ?>
                    <span class="ms-auto text-muted-sa" style="font-size:.82rem">Total revenue: <strong style="color:#10B981">Rp <?= number_format($total_revenue, 0, ',', '.') ?></strong></span>
                </form>
            </div>
        </div>

        <div class="sa-card">
            <div style="overflow-x:auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lembaga / Pembeli</th>
                            <th>Paket</th>
                            <th>Tagihan</th>
                            <th>Subdomain</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8"><div class="empty-state">Tidak ada order ditemukan.</div></td></tr>
                    <?php else: foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($o['nama_lembaga']) ?></strong><br>
                            <small class="text-muted-sa"><?= htmlspecialchars($o['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($o['paket_nama'] ?? '—') ?></td>
                        <td style="color:#fff;font-weight:600">Rp <?= number_format($o['harga_bayar'], 0, ',', '.') ?></td>
                        <td><code style="color:var(--cyan);font-size:.8rem"><?= htmlspecialchars($o['subdomain_request'] ?? '—') ?></code></td>
                        <td><span class="sa-badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <?php if ($o['status'] === 'pending'): ?>
                                <a href="finance.php?highlight=<?= $o['id'] ?>" class="btn-sa-primary" style="font-size:.78rem;padding:.3rem .7rem">Verifikasi</a>
                                <?php else: ?>
                                <span style="color:var(--text-muted);font-size:.8rem;margin-right:.5rem">Selesai</span>
                                <?php endif; ?>
                                <button onclick="showHapusOrderModal(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['nama_lembaga'])) ?>')" class="btn-sa-danger" style="font-size:.78rem;padding:.3rem .5rem" title="Hapus Order">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
        <form method="POST">
            <input type="hidden" name="action" value="hapus_order">
            <input type="hidden" name="order_id" id="hapusOrderId">
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" onclick="closeHapusOrderModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-danger" style="padding:.5rem 1.2rem">
                    🗑️ Ya, Hapus
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus Semua Order -->
<div class="sa-modal-backdrop" id="modalHapusSemua">
    <div class="sa-modal" style="max-width:440px">
        <div class="sa-modal-title" style="color:#EF4444">⚠️ Hapus Semua Order</div>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:1rem;margin-bottom:1.2rem;font-size:.875rem">
            <strong style="color:#EF4444">PERINGATAN KRITIS!</strong><br>
            <span style="color:var(--text-sub)">Tindakan ini akan menghapus <strong>semua data order</strong> dan seluruh file bukti pembayaran di server secara permanen!</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="hapus_semua_order">
            <div class="sa-form-group">
                <label>Ketik <strong style="color:#fff">HAPUS SEMUA</strong> untuk konfirmasi:</label>
                <input type="text" name="konfirmasi" class="sa-form-control" required placeholder="HAPUS SEMUA" autocomplete="off">
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <button type="button" onclick="closeHapusSemuaModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-danger" style="padding:.5rem 1.2rem">
                    🗑️ Ya, Hapus Semua
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

function showHapusSemuaModal() {
    document.getElementById('modalHapusSemua').classList.add('show');
}
function closeHapusSemuaModal() {
    document.getElementById('modalHapusSemua').classList.remove('show');
}
document.getElementById('modalHapusSemua').addEventListener('click', function(e) {
    if (e.target === this) closeHapusSemuaModal();
});
</script>
</body>
</html>
