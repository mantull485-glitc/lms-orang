<?php
require_once 'auth_guard.php';
require_once '../config/provisioner.php';

$flash = $_SESSION['flash_tenants'] ?? null;
unset($_SESSION['flash_tenants']);

// ── HANDLE ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['tenant_id'])) {
    $tid    = (int)$_POST['tenant_id'];
    $action = $_POST['action'];

    $stmt = $pdo_global->prepare("SELECT * FROM tenants WHERE id=?");
    $stmt->execute([$tid]);
    $tenant = $stmt->fetch();

    if ($tenant) {
        if ($action === 'nonaktifkan') {
            $alasan = htmlspecialchars(trim($_POST['alasan'] ?? ''));
            $pdo_global->prepare("UPDATE tenants SET status='nonaktif', alasan_nonaktif=?, dinonaktifkan_oleh=?, tanggal_nonaktif=NOW() WHERE id=?")
                       ->execute([$alasan, $_SESSION['superadmin_id'], $tid]);
            $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,?)")
                       ->execute([$tid, $tenant['status'], 'nonaktif', $alasan ?: 'Dinonaktifkan oleh admin', $_SESSION['superadmin_id']]);
            $_SESSION['flash_tenants'] = ['type'=>'warning','msg'=>'Tenant "'.$tenant['nama_lembaga'].'" berhasil dinonaktifkan.'];

        } elseif ($action === 'aktifkan') {
            $pdo_global->prepare("UPDATE tenants SET status='aktif', alasan_nonaktif=NULL, dinonaktifkan_oleh=NULL, tanggal_nonaktif=NULL WHERE id=?")
                       ->execute([$tid]);
            $pdo_global->prepare("INSERT INTO tenant_status_logs (tenant_id, status_lama, status_baru, alasan, dilakukan_oleh) VALUES (?,?,?,?,?)")
                       ->execute([$tid, $tenant['status'], 'aktif', 'Diaktifkan kembali oleh admin', $_SESSION['superadmin_id']]);
            $_SESSION['flash_tenants'] = ['type'=>'success','msg'=>'Tenant "'.$tenant['nama_lembaga'].'" berhasil diaktifkan kembali.'];

        } elseif ($action === 'deploy') {
            // Cari order accepted terkait tenant ini
            $ord = $pdo_global->prepare("SELECT id FROM orders WHERE tenant_id = ? AND status = 'diterima' ORDER BY id DESC LIMIT 1");
            $ord->execute([$tid]);
            $order_row = $ord->fetch();
            if (!$order_row) {
                $ord2 = $pdo_global->prepare("SELECT id FROM orders WHERE email = ? AND status = 'diterima' ORDER BY id DESC LIMIT 1");
                $ord2->execute([$tenant['email']]);
                $order_row = $ord2->fetch();
            }
            if ($order_row) {
                $result = provisionTenant($order_row['id'], $pdo_global);
                if ($result['success']) {
                    $_SESSION['flash_tenants'] = ['type'=>'success','msg'=>'🚀 Tenant "'.$tenant['nama_lembaga'].'" berhasil di-deploy! URL Admin: <code>tenants/'.$result['subdomain'].'/admin/</code> &nbsp;·&nbsp; Password default: <code>admin123</code>'];
                } else {
                    $_SESSION['flash_tenants'] = ['type'=>'danger','msg'=>'Deploy gagal: '.$result['message']];
                }
            } else {
                // Tidak ada order — buat folder dari template saja
                $sub  = preg_replace('/[^a-z0-9_]/', '', strtolower($tenant['subdomain'] ?? ''));
                $base = dirname(__DIR__);
                if (!empty($sub)) {
                    $tf = $base . '/tenants/' . $sub;
                    if (!is_dir($tf)) {
                        copyDirectory($base . '/tenants/_template', $tf);
                        $dbcfg = generateTenantConfig(SA_DB_HOST, $tenant['db_name'] ?? ('tenant_'.$tid.'_db'), SA_DB_USER, SA_DB_PASS);
                        file_put_contents($tf . '/config/database.php', $dbcfg);
                        $stcfg = generateStatusCheck($sub);
                        file_put_contents($tf . '/config/status_check.php', $stcfg);
                        $pdo_global->prepare("UPDATE tenants SET folder_path=? WHERE id=?")->execute(['tenants/'.$sub, $tid]);
                        $_SESSION['flash_tenants'] = ['type'=>'success','msg'=>'🚀 Folder tenant "'.$tenant['nama_lembaga'].'" berhasil dibuat dari template!'];
                    } else {
                        $_SESSION['flash_tenants'] = ['type'=>'warning','msg'=>'Folder sudah ada: <code>tenants/'.$sub.'</code>'];
                    }
                } else {
                    $_SESSION['flash_tenants'] = ['type'=>'danger','msg'=>'Tidak ada order atau subdomain valid untuk tenant ini.'];
                }
            }

        } elseif ($action === 'hapus') {
            $hapus_db     = ($_POST['hapus_db']     ?? '0') === '1';
            $hapus_folder = ($_POST['hapus_folder'] ?? '0') === '1';

            // Hapus database tenant
            if ($hapus_db && !empty($tenant['db_name'])) {
                try {
                    $raw = new PDO('mysql:host=localhost;charset=utf8mb4', SA_DB_USER, SA_DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                    $raw->exec("DROP DATABASE IF EXISTS `" . preg_replace('/[^a-z0-9_]/', '', $tenant['db_name']) . "`");
                } catch (PDOException $e) { /* lanjut meski gagal */ }
            }

            // Hapus folder tenant
            if ($hapus_folder && !empty($tenant['folder_path'])) {
                $folder = dirname(__DIR__) . '/' . ltrim($tenant['folder_path'], '/');
                if (is_dir($folder)) {
                    // Hapus rekursif
                    $it = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
                    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($files as $file) {
                        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                    }
                    rmdir($folder);
                }
            }

            // Hapus dari database global
            $pdo_global->prepare("DELETE FROM tenant_status_logs WHERE tenant_id=?")->execute([$tid]);
            $pdo_global->prepare("UPDATE orders SET tenant_id=NULL WHERE tenant_id=?")->execute([$tid]);
            $pdo_global->prepare("DELETE FROM tenants WHERE id=?")->execute([$tid]);

            $msg = 'Tenant "' . htmlspecialchars($tenant['nama_lembaga']) . '" berhasil dihapus dari sistem.';
            if ($hapus_db)     $msg .= ' Database dihapus.';
            if ($hapus_folder) $msg .= ' Folder dihapus.';
            $_SESSION['flash_tenants'] = ['type'=>'danger','msg'=>$msg];
        }
    }
    header('Location: tenants.php'); exit;
}

// ── FILTER & SEARCH ──
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['q'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($filter_status) { $where .= " AND t.status = ?"; $params[] = $filter_status; }
if ($search)        { $where .= " AND (t.nama_lembaga LIKE ? OR t.email LIKE ? OR t.subdomain LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$stmt = $pdo_global->prepare("SELECT t.*, p.nama as paket_nama FROM tenants t LEFT JOIN packages p ON t.package_id=p.id $where ORDER BY t.created_at DESC");
$stmt->execute($params);
$tenants = $stmt->fetchAll();

// Detail tenant untuk modal log
$detail_tenant = null;
$status_logs = [];
if (isset($_GET['detail'])) {
    $d = $pdo_global->prepare("SELECT t.*, p.nama as paket_nama FROM tenants t LEFT JOIN packages p ON t.package_id=p.id WHERE t.id=?");
    $d->execute([(int)$_GET['detail']]);
    $detail_tenant = $d->fetch();
    if ($detail_tenant) {
        $l = $pdo_global->prepare("SELECT sl.*, sa.nama as admin_nama FROM tenant_status_logs sl LEFT JOIN superadmins sa ON sl.dilakukan_oleh=sa.id WHERE sl.tenant_id=? ORDER BY sl.created_at DESC");
        $l->execute([$detail_tenant['id']]);
        $status_logs = $l->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tenant – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Tenant / LPK</div>
        <div class="sa-topbar-actions">
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>

    <div class="sa-content">

        <?php if ($flash): ?>
        <div class="sa-alert sa-alert-<?= $flash['type'] === 'warning' ? 'warning' : ($flash['type'] === 'danger' ? 'danger' : 'success') ?>">
            <?= $flash['msg'] ?>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="sa-card mb-3">
            <div class="sa-card-body" style="padding:1rem 1.5rem">
                <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="text" name="q" class="sa-form-control" style="width:240px" placeholder="Cari nama / email / subdomain..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status" class="sa-form-control" style="width:160px">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $filter_status==='aktif'?'selected':'' ?>>Aktif</option>
                        <option value="nonaktif" <?= $filter_status==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                        <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
                        <option value="expired" <?= $filter_status==='expired'?'selected':'' ?>>Expired</option>
                        <option value="suspend" <?= $filter_status==='suspend'?'selected':'' ?>>Suspend</option>
                    </select>
                    <button type="submit" class="btn-sa-primary">Filter</button>
                    <?php if ($filter_status || $search): ?>
                    <a href="tenants.php" class="btn-sa-outline">Reset</a>
                    <?php endif; ?>
                    <span class="ms-auto text-muted-sa" style="font-size:.82rem"><?= count($tenants) ?> tenant ditemukan</span>
                </form>
            </div>
        </div>

        <!-- Tenant Table -->
        <div class="sa-card">
            <div style="overflow-x:auto">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Lembaga</th>
                            <th>Subdomain / URL</th>
                            <th>Paket</th>
                            <th>Status</th>
                            <th>Aktif Hingga</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                                Tidak ada tenant ditemukan.
                            </div>
                        </td></tr>
                        <?php else: foreach ($tenants as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['nama_lembaga']) ?></strong><br>
                                <small class="text-muted-sa"><?= htmlspecialchars($t['nama_pemilik']) ?> · <?= htmlspecialchars($t['email']) ?></small>
                            </td>
                            <td>
                                <?php if ($t['subdomain']): ?>
                                <code style="color:var(--cyan);font-size:.8rem"><?= htmlspecialchars($t['subdomain']) ?></code><br>
                                <a href="../tenants/<?= htmlspecialchars($t['subdomain']) ?>/" target="_blank" style="font-size:.78rem;color:var(--text-muted);text-decoration:none">
                                    Buka platform ↗
                                </a>
                                <?php else: ?>
                                <span class="text-muted-sa">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($t['paket_nama'] ?? '—') ?></td>
                            <td><span class="sa-badge badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td>
                                <?php if ($t['tanggal_expire']): ?>
                                <?php $exp = strtotime($t['tanggal_expire']); $warn = $exp < strtotime('+7 days'); ?>
                                <span style="color:<?= $warn ? '#F59E0B' : 'var(--text-sub)' ?>"><?= date('d M Y', $exp) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $folder_exists = !empty($t['folder_path']) && is_dir(dirname(__DIR__) . '/' . ltrim($t['folder_path'], '/'));
                                ?>
                                <div class="d-flex gap-1 flex-wrap">

                                    <!-- Buka Admin (aktif + folder ada) -->
                                    <?php if ($t['status'] === 'aktif' && $folder_exists && !empty($t['subdomain'])): ?>
                                    <a href="../tenants/<?= htmlspecialchars($t['subdomain']) ?>/admin/" target="_blank"
                                       class="btn-sa-outline" style="font-size:.75rem;padding:.3rem .6rem;border-color:var(--cyan);color:var(--cyan)" title="Buka Panel Admin Tenant">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        Admin
                                    </a>
                                    <?php endif; ?>

                                    <!-- Deploy (folder belum ada) -->
                                    <?php if (!$folder_exists && !empty($t['subdomain'])): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Deploy tenant ini sekarang?')">
                                        <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="action" value="deploy">
                                        <button type="submit" class="btn-sa-success" style="font-size:.75rem;padding:.3rem .6rem">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            Deploy
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Detail/Log -->
                                    <a href="?detail=<?= $t['id'] ?><?= $filter_status ? '&status='.$filter_status : '' ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn-sa-outline" style="font-size:.75rem;padding:.3rem .6rem" title="Riwayat Status">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        Log
                                    </a>

                                    <?php if ($t['status'] === 'aktif'): ?>
                                    <!-- Nonaktifkan -->
                                    <button onclick="showNonaktifModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nama_lembaga'])) ?>')" class="btn-sa-danger" style="font-size:.75rem;padding:.3rem .6rem">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        Nonaktifkan
                                    </button>
                                    <?php elseif (in_array($t['status'], ['nonaktif','suspend','expired'])): ?>
                                    <!-- Aktifkan Kembali -->
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="action" value="aktifkan">
                                        <button type="submit" class="btn-sa-success" style="font-size:.75rem;padding:.3rem .6rem" onclick="return confirm('Aktifkan kembali tenant ini?')">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Aktifkan
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Hapus Tenant -->
                                    <button onclick="showHapusModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['nama_lembaga'])) ?>', '<?= htmlspecialchars(addslashes($t['db_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($t['folder_path'] ?? '')) ?>')" 
                                        class="btn-sa-outline" style="font-size:.75rem;padding:.3rem .6rem;border-color:#EF4444;color:#EF4444" title="Hapus Tenant">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Hapus
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

<!-- Modal Nonaktifkan -->
<div class="sa-modal-backdrop" id="modalNonaktif">
    <div class="sa-modal">
        <div class="sa-modal-title">⚠️ Nonaktifkan Tenant</div>
        <p style="color:var(--text-sub);font-size:.9rem;margin-bottom:1.2rem">
            Anda akan menonaktifkan: <strong id="modalNamaTenant" style="color:#fff"></strong><br>
            Platform mereka tidak dapat diakses hingga diaktifkan kembali.
        </p>
        <form method="POST" id="formNonaktif">
            <input type="hidden" name="action" value="nonaktifkan">
            <input type="hidden" name="tenant_id" id="modalTenantId">
            <div class="sa-form-group">
                <label>Alasan Penonaktifan <span style="color:var(--text-muted)">(opsional, akan ditampilkan ke pengunjung)</span></label>
                <textarea name="alasan" class="sa-form-control" rows="3" placeholder="Contoh: Masa berlaku habis, silakan perpanjang paket..."></textarea>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" onclick="closeModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" class="btn-sa-danger" style="padding:.5rem 1.2rem">Ya, Nonaktifkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail / Log Status -->
<?php if ($detail_tenant): ?>
<div class="sa-modal-backdrop show" id="modalDetail">
    <div class="sa-modal" style="max-width:560px">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="sa-modal-title" style="margin:0">Riwayat Status Tenant</div>
            <a href="tenants.php<?= $filter_status ? '?status='.$filter_status : '' ?>" style="color:var(--text-muted);text-decoration:none;font-size:1.3rem">×</a>
        </div>
        <div style="background:var(--navy);border-radius:10px;padding:1rem;margin-bottom:1.2rem">
            <strong style="color:#fff"><?= htmlspecialchars($detail_tenant['nama_lembaga']) ?></strong><br>
            <small class="text-muted-sa"><?= htmlspecialchars($detail_tenant['email']) ?> · <?= htmlspecialchars($detail_tenant['subdomain'] ?? '') ?></small><br>
            <span class="sa-badge badge-<?= $detail_tenant['status'] ?> mt-2" style="display:inline-block"><?= ucfirst($detail_tenant['status']) ?></span>
            <?php if ($detail_tenant['alasan_nonaktif']): ?>
            <div style="margin-top:.6rem;font-size:.82rem;color:#94A3B8">
                Alasan: <?= htmlspecialchars($detail_tenant['alasan_nonaktif']) ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.5rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Riwayat Perubahan</div>
        <div style="max-height:280px;overflow-y:auto">
            <?php if (empty($status_logs)): ?>
            <div style="color:var(--text-muted);font-size:.85rem;padding:.5rem 0">Belum ada riwayat perubahan status.</div>
            <?php else: foreach ($status_logs as $log): ?>
            <div style="display:flex;gap:10px;padding:.6rem 0;border-bottom:1px solid var(--border)">
                <div style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:<?= $log['status_baru']==='aktif'?'#10B981':'#EF4444' ?>;margin-top:5px"></div>
                <div>
                    <div style="font-size:.83rem;color:var(--text)">
                        <?= $log['status_lama'] ? '<span style="color:var(--text-muted)">'.ucfirst($log['status_lama']).'</span> → ' : '' ?>
                        <strong style="color:<?= $log['status_baru']==='aktif'?'#10B981':'#EF4444' ?>"><?= ucfirst($log['status_baru']) ?></strong>
                        <?php if ($log['admin_nama']): ?><span style="color:var(--text-muted)"> oleh <?= htmlspecialchars($log['admin_nama']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($log['alasan']): ?><div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($log['alasan']) ?></div><?php endif; ?>
                    <div style="font-size:.72rem;color:#475569;margin-top:2px"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="text-end mt-3">
            <a href="tenants.php" class="btn-sa-outline">Tutup</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Hapus Tenant -->
<div class="sa-modal-backdrop" id="modalHapus">
    <div class="sa-modal" style="max-width:480px">
        <div class="sa-modal-title" style="color:#EF4444">🗑️ Hapus Tenant Permanen</div>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:1rem;margin-bottom:1.2rem">
            <strong style="color:#EF4444">⚠️ Tindakan ini tidak dapat dibatalkan!</strong><br>
            <span style="color:var(--text-sub);font-size:.875rem">Anda akan menghapus tenant: <strong id="hapusNamaTenant" style="color:#fff"></strong></span>
        </div>
        <form method="POST" id="formHapus">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="tenant_id" id="hapusTenantId">

            <!-- Opsi Hapus -->
            <div style="margin-bottom:1rem">
                <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.6rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Pilih data yang ikut dihapus:</div>
                
                <label style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem;border-radius:10px;border:1px solid var(--border);cursor:pointer;margin-bottom:.5rem" id="labelHapusDb">
                    <input type="checkbox" name="hapus_db" value="1" id="cbHapusDb" style="margin-top:2px;accent-color:#EF4444">
                    <div>
                        <div style="color:#fff;font-size:.875rem;font-weight:600">🗄️ Hapus Database Tenant</div>
                        <div style="color:var(--text-muted);font-size:.78rem" id="hapusDbName">Semua data kelas, siswa, sertifikat, dan keuangan tenant ini akan hilang.</div>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem;border-radius:10px;border:1px solid var(--border);cursor:pointer" id="labelHapusFolder">
                    <input type="checkbox" name="hapus_folder" value="1" id="cbHapusFolder" style="margin-top:2px;accent-color:#EF4444">
                    <div>
                        <div style="color:#fff;font-size:.875rem;font-weight:600">📁 Hapus Folder Platform</div>
                        <div style="color:var(--text-muted);font-size:.78rem" id="hapusFolderPath">File PHP, gambar, dan upload tenant akan dihapus dari server.</div>
                    </div>
                </label>
            </div>

            <!-- Konfirmasi ketik nama -->
            <div class="sa-form-group">
                <label style="font-size:.82rem">Ketik nama lembaga untuk konfirmasi: <strong id="hapusKonfirmasiNama" style="color:#EF4444"></strong></label>
                <input type="text" id="inputKonfirmasi" class="sa-form-control" placeholder="Ketik nama lembaga..." oninput="checkKonfirmasi()">
            </div>

            <div class="d-flex gap-2 justify-content-end mt-3">
                <button type="button" onclick="closeHapusModal()" class="btn-sa-outline">Batal</button>
                <button type="submit" id="btnHapusSubmit" class="btn-sa-danger" style="padding:.5rem 1.2rem;opacity:.4;pointer-events:none" disabled>
                    🗑️ Ya, Hapus Permanen
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showNonaktifModal(id, nama) {
    document.getElementById('modalTenantId').value = id;
    document.getElementById('modalNamaTenant').textContent = nama;
    document.getElementById('modalNonaktif').classList.add('show');
}
function closeModal() {
    document.getElementById('modalNonaktif').classList.remove('show');
}
document.getElementById('modalNonaktif').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

let _hapusNama = '';
function showHapusModal(id, nama, dbName, folderPath) {
    _hapusNama = nama;
    document.getElementById('hapusTenantId').value  = id;
    document.getElementById('hapusNamaTenant').textContent   = nama;
    document.getElementById('hapusKonfirmasiNama').textContent = nama;
    document.getElementById('hapusDbName').textContent = dbName ? 'Database: ' + dbName + ' — semua data kelas, siswa, sertifikat akan hilang.' : 'Tidak ada database yang terdeteksi.';
    document.getElementById('hapusFolderPath').textContent = folderPath ? 'Folder: ' + folderPath + ' — file PHP, gambar, upload akan dihapus.' : 'Tidak ada folder yang terdeteksi.';
    document.getElementById('inputKonfirmasi').value = '';
    document.getElementById('cbHapusDb').checked = false;
    document.getElementById('cbHapusFolder').checked = false;
    checkKonfirmasi();
    document.getElementById('modalHapus').classList.add('show');
}
function closeHapusModal() {
    document.getElementById('modalHapus').classList.remove('show');
}
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeHapusModal();
});
function checkKonfirmasi() {
    const val = document.getElementById('inputKonfirmasi').value.trim();
    const btn = document.getElementById('btnHapusSubmit');
    if (val === _hapusNama) {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    } else {
        btn.disabled = true;
        btn.style.opacity = '.4';
        btn.style.pointerEvents = 'none';
    }
}
</script>
</body>
</html>
