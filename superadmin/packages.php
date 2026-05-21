<?php
require_once 'auth_guard.php';

$flash = $_SESSION['flash_pkg'] ?? null;
unset($_SESSION['flash_pkg']);

// ── HANDLE ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $nama        = trim($_POST['nama'] ?? '');
        $harga       = (int)str_replace(['.', ','], '', $_POST['harga'] ?? 0);
        $harga_thn   = (int)str_replace(['.', ','], '', $_POST['harga_tahunan'] ?? 0);
        $max_kelas   = (int)($_POST['max_kelas'] ?? 10);
        $max_users   = (int)($_POST['max_users'] ?? 100);
        $is_popular  = isset($_POST['is_popular']) ? 1 : 0;
        $status      = $_POST['status'] ?? 'aktif';
        $fitur_raw   = array_filter(array_map('trim', explode("\n", $_POST['fitur'] ?? '')));
        $fitur_json  = json_encode(array_values($fitur_raw));

        if ($action === 'tambah') {
            $pdo_global->prepare("INSERT INTO packages (nama,harga,harga_tahunan,max_kelas,max_users,fitur,is_popular,status) VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$nama, $harga, $harga_thn ?: null, $max_kelas, $max_users, $fitur_json, $is_popular, $status]);
            $_SESSION['flash_pkg'] = ['type'=>'success','msg'=>'Paket "'.$nama.'" berhasil ditambahkan.'];
        } else {
            $id = (int)$_POST['id'];
            $pdo_global->prepare("UPDATE packages SET nama=?,harga=?,harga_tahunan=?,max_kelas=?,max_users=?,fitur=?,is_popular=?,status=? WHERE id=?")
                       ->execute([$nama, $harga, $harga_thn ?: null, $max_kelas, $max_users, $fitur_json, $is_popular, $status, $id]);
            $_SESSION['flash_pkg'] = ['type'=>'success','msg'=>'Paket berhasil diperbarui.'];
        }
    } elseif ($action === 'hapus') {
        $id = (int)$_POST['id'];
        // Cek apakah ada tenant yang pakai paket ini
        $used = $pdo_global->prepare("SELECT COUNT(*) FROM tenants WHERE package_id=?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $_SESSION['flash_pkg'] = ['type'=>'danger','msg'=>'Paket tidak bisa dihapus karena masih digunakan oleh tenant.'];
        } else {
            $pdo_global->prepare("DELETE FROM packages WHERE id=?")->execute([$id]);
            $_SESSION['flash_pkg'] = ['type'=>'warning','msg'=>'Paket berhasil dihapus.'];
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $pdo_global->prepare("UPDATE packages SET status = IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        $_SESSION['flash_pkg'] = ['type'=>'success','msg'=>'Status paket diperbarui.'];
    }

    header('Location: packages.php'); exit;
}

// ── Edit mode ──
$edit_pkg = null;
if (isset($_GET['edit'])) {
    $s = $pdo_global->prepare("SELECT * FROM packages WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit_pkg = $s->fetch();
}

$packages = $pdo_global->query("SELECT p.*, (SELECT COUNT(*) FROM tenants t WHERE t.package_id=p.id) as tenant_count FROM packages p ORDER BY p.harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket & Harga – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Paket & Harga</div>
        <div class="sa-topbar-actions">
            <button onclick="openModal('modalTambah')" class="btn-sa-primary">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Paket
            </button>
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">

        <?php if ($flash): ?>
        <div class="sa-alert sa-alert-<?= $flash['type'] ?>">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- Package Cards -->
        <div class="row g-3 mb-4">
            <?php foreach ($packages as $pkg):
                $fitur = json_decode($pkg['fitur'] ?? '[]', true) ?: [];
            ?>
            <div class="col-md-4">
                <div class="sa-card h-100" style="<?= $pkg['is_popular'] ? 'border-color:var(--orange)' : '' ?>">
                    <?php if ($pkg['is_popular']): ?>
                    <div style="background:var(--orange);color:#fff;text-align:center;font-size:.75rem;font-weight:700;padding:.3rem;letter-spacing:1px">★ PALING POPULER</div>
                    <?php endif; ?>
                    <div class="sa-card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 style="color:#fff;font-weight:700;margin:0"><?= htmlspecialchars($pkg['nama']) ?></h5>
                            <span class="sa-badge badge-<?= $pkg['status'] ?>"><?= ucfirst($pkg['status']) ?></span>
                        </div>
                        <div style="font-size:1.6rem;font-weight:700;color:var(--orange);margin:.5rem 0">
                            Rp <?= number_format($pkg['harga'], 0, ',', '.') ?>
                            <span style="font-size:.8rem;color:var(--text-muted);font-weight:400">/bulan</span>
                        </div>
                        <?php if ($pkg['harga_tahunan']): ?>
                        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.8rem">
                            Tahunan: Rp <?= number_format($pkg['harga_tahunan'], 0, ',', '.') ?>
                        </div>
                        <?php endif; ?>

                        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem">
                            Max <?= $pkg['max_kelas'] === 999 ? '∞' : $pkg['max_kelas'] ?> kelas · Max <?= $pkg['max_users'] === 9999 ? '∞' : $pkg['max_users'] ?> siswa
                        </div>

                        <ul style="list-style:none;padding:0;margin:.75rem 0;font-size:.83rem;color:var(--text-sub)">
                            <?php foreach ($fitur as $f): ?>
                            <li style="padding:.2rem 0">
                                <span style="color:#10B981;margin-right:6px">✓</span><?= htmlspecialchars($f) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.75rem">
                            Digunakan oleh <strong style="color:var(--text)"><?= $pkg['tenant_count'] ?></strong> tenant
                        </div>

                        <div class="d-flex gap-2">
                            <a href="?edit=<?= $pkg['id'] ?>" class="btn-sa-outline" style="font-size:.8rem;padding:.35rem .8rem;flex:1;justify-content:center">Edit</a>
                            <form method="POST" style="flex:1">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <button type="submit" class="btn-sa-outline w-100" style="font-size:.8rem;padding:.35rem .8rem;color:<?= $pkg['status']==='aktif' ? '#F59E0B' : '#10B981' ?>;border-color:<?= $pkg['status']==='aktif' ? 'rgba(245,158,11,.3)' : 'rgba(16,185,129,.3)' ?>">
                                    <?= $pkg['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                            <?php if ($pkg['tenant_count'] == 0): ?>
                            <form method="POST" onsubmit="return confirm('Hapus paket ini?')">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <button type="submit" class="btn-sa-danger" style="font-size:.8rem;padding:.35rem .8rem">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Modal Tambah Paket -->
<div class="sa-modal-backdrop <?= !$edit_pkg ? '' : '' ?>" id="modalTambah">
    <div class="sa-modal" style="max-width:520px;max-height:90vh;overflow-y:auto">
        <div class="sa-modal-title">Tambah Paket Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <?php include '_form_package.php'; ?>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" onclick="closeModal('modalTambah')" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-primary">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Paket -->
<?php if ($edit_pkg):
    $edit_fitur = implode("\n", json_decode($edit_pkg['fitur'] ?? '[]', true) ?: []);
?>
<div class="sa-modal-backdrop show" id="modalEdit">
    <div class="sa-modal" style="max-width:520px;max-height:90vh;overflow-y:auto">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="sa-modal-title" style="margin:0">Edit Paket</div>
            <a href="packages.php" style="color:var(--text-muted);text-decoration:none;font-size:1.4rem">×</a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $edit_pkg['id'] ?>">
            <?php
            // Gunakan data edit untuk form
            $form_pkg = $edit_pkg;
            $form_fitur = $edit_fitur;
            include '_form_package.php';
            ?>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <a href="packages.php" class="btn-sa-outline">Batal</a>
                <button type="submit" class="btn-sa-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.sa-modal-backdrop').forEach(el => {
    el.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('show'); });
});
</script>
</body>
</html>
