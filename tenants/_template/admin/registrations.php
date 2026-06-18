<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
$tenant_id = $GLOBALS['tenant_id'] ?? 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'list';

// Handle Delete Registration (Moved to top for priority)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        $stmt_info = $pdo->prepare("SELECT bukti_bayar, user_id, class_id FROM registrations WHERE id = ? AND tenant_id = ?");
        $stmt_info->execute([$id, $tenant_id]);
        $reg_info = $stmt_info->fetch();

        if ($reg_info) {
            // Delete certificate first
            $pdo->prepare("DELETE FROM certificates WHERE user_id = ? AND class_id = ? AND tenant_id = ?")->execute([$reg_info['user_id'], $reg_info['class_id'], $tenant_id]);
            
            // Delete registration
            $stmt_del = $pdo->prepare("DELETE FROM registrations WHERE id = ? AND tenant_id = ?");
            $stmt_del->execute([$id, $tenant_id]);

            if ($stmt_del->rowCount() > 0) {
                if (!empty($reg_info['bukti_bayar']) && file_exists('../' . $reg_info['bukti_bayar'])) {
                    @unlink('../' . $reg_info['bukti_bayar']);
                }
                $_SESSION['flash_message'] = "Data pendaftaran berhasil dihapus.";
            } else {
                $_SESSION['flash_error'] = "Gagal menghapus data dari database (ID: $id).";
            }
        } else {
            $_SESSION['flash_error'] = "Data tidak ditemukan (ID: $id).";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
    }
    
    header("Location: registrations.php");
    exit;
}

// Handle Add Registration by Admin
if ($action === 'add_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $class_id = intval($_POST['class_id']);

    // Fetch class price to record harga_saat_daftar
    $stmt_cls = $pdo->prepare("SELECT harga, harga_spesial FROM classes WHERE id = ? AND tenant_id = ?");
    $stmt_cls->execute([$class_id, $tenant_id]);
    $cls = $stmt_cls->fetch();
    $harga = $cls ? (($cls['harga_spesial'] !== null) ? $cls['harga_spesial'] : $cls['harga']) : 0;

    $stmt_check = $pdo->prepare("SELECT id FROM registrations WHERE user_id = ? AND class_id = ? AND tenant_id = ?");
    $stmt_check->execute([$user_id, $class_id, $tenant_id]);
    
    if ($stmt_check->fetch()) {
        $_SESSION['flash_error'] = "Peserta sudah terdaftar di kelas ini!";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO registrations (tenant_id, user_id, class_id, status, harga_saat_daftar, metode_pembayaran, tanggal_konfirmasi)
            VALUES (?, ?, ?, 'diterima', ?, 'manual_admin', NOW())
        ");
        if ($stmt->execute([$tenant_id, $user_id, $class_id, $harga])) {
            $_SESSION['flash_message'] = "Berhasil! Peserta telah ditambahkan ke kelas tersebut.";
        } else {
            $_SESSION['flash_error'] = "Gagal mendaftarkan peserta.";
        }
    }
    header("Location: registrations.php");
    exit;
}

// Handle Update Status
if ($action === 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'diterima', 'ditolak', 'selesai'])) {
        if ($status === 'diterima') {
            $stmt = $pdo->prepare("UPDATE registrations SET status = ?, tanggal_konfirmasi = NOW() WHERE id = ? AND tenant_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE registrations SET status = ? WHERE id = ? AND tenant_id = ?");
        }
        $stmt->execute([$status, $id, $tenant_id]);
        $_SESSION['flash_message'] = "Status pendaftaran berhasil diubah menjadi " . ucfirst($status) . "!";
    }
    
    header("Location: registrations.php");
    exit;
}

// Fetch Registrations
$stmt_regs = $pdo->prepare("
    SELECT r.*, u.nama, u.email, u.no_hp, c.nama_kelas, c.jadwal, c.kategori 
    FROM registrations r 
    JOIN users u ON r.user_id = u.id 
    JOIN classes c ON r.class_id = c.id 
    WHERE r.tenant_id = ?
    ORDER BY r.tanggal_daftar DESC
");
$stmt_regs->execute([$tenant_id]);
$registrations = $stmt_regs->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Kelola Pendaftaran - Admin LPK Lunarica</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <!-- FontAwesome 5.15.4 (Stable) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">

            <?php if ($action === 'add'): ?>
                <?php
                    // Fetch users for dropdown
                    $stmt_all_users = $pdo->prepare("SELECT id, nama, email FROM users WHERE role = 'user' AND tenant_id = ? ORDER BY nama ASC");
                    $stmt_all_users->execute([$tenant_id]);
                    $all_users = $stmt_all_users->fetchAll();
                    
                    // Fetch classes for dropdown
                    $stmt_all_classes = $pdo->prepare("SELECT id, nama_kelas, jadwal FROM classes WHERE tenant_id = ? ORDER BY jadwal ASC");
                    $stmt_all_classes->execute([$tenant_id]);
                    $all_classes = $stmt_all_classes->fetchAll();
                ?>
                <!-- Header Add -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Daftarkan Peserta Manual</h2>
                        <p class="text-muted mb-0">Masukkan peserta ke dalam pelatihan secara langsung.</p>
                    </div>
                </div>

                <div class="modern-card p-4">
                    <form action="registrations.php?action=add_process" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Pilih Peserta (User)</label>
                                <select name="user_id" class="form-select form-control-modern" required>
                                    <option value="" disabled selected>Cari nama peserta...</option>
                                    <?php foreach($all_users as $usr): ?>
                                        <option value="<?= $usr['id']; ?>"><?= htmlspecialchars($usr['nama']); ?> (<?= htmlspecialchars($usr['email']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Pilih Kelas Pelatihan</label>
                                <select name="class_id" class="form-select form-control-modern" required>
                                    <option value="" disabled selected>Pilih kelas aktif...</option>
                                    <?php foreach($all_classes as $cls): ?>
                                        <option value="<?= $cls['id']; ?>"><?= htmlspecialchars($cls['nama_kelas']); ?> - <?= date('d M Y', strtotime($cls['jadwal'])); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <hr class="my-5 opacity-10">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">Konfirmasi Pendaftaran</button>
                            <a href="registrations.php" class="btn btn-light px-5 py-3 rounded-4 border-0">Batal</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Header List -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Log Pendaftaran</h2>
                        <p class="text-muted mb-0">Tinjau dan proses pendaftaran peserta yang masuk.</p>
                    </div>
                    <a href="registrations.php?action=add" class="btn btn-primary rounded-pill px-4 shadow-none">
                        <i class="fas fa-plus me-1"></i> Daftar Manual
                    </a>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="modern-card">
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>Peserta</th>
                                    <th>Pelatihan</th>
                                    <th>Status & Bukti</th>
                                    <th class="text-end">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($registrations) > 0): ?>
                                    <?php foreach($registrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold small text-dark"><?= htmlspecialchars($reg['nama']); ?></div>
                                                <div class="extra-small text-muted mt-1"><?= htmlspecialchars($reg['email']); ?></div>
                                                <div class="extra-small text-muted"><?= htmlspecialchars($reg['no_hp']); ?></div>
                                            </td>
                                            <td>
                                                <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($reg['kategori'] ?? 'Umum'); ?></div>
                                                <div class="fw-bold small text-truncate" style="max-width:200px;"><?= htmlspecialchars($reg['nama_kelas']); ?></div>
                                                <div class="extra-small text-muted mt-1"><?= date('d/m/Y H:i', strtotime($reg['tanggal_daftar'])); ?></div>
                                            </td>
                                            <td>
                                                <div class="mb-2">
                                                    <span class="badge badge-premium status-<?= $reg['status'] ?> extra-small">
                                                        <?= ucfirst($reg['status']) ?>
                                                    </span>
                                                    <?php if(!empty($reg['metode_pembayaran'])): ?>
                                                        <?php 
                                                            $method_label = '';
                                                            $method_class = 'bg-secondary';
                                                            switch($reg['metode_pembayaran']) {
                                                                case 'transfer_bank': $method_label = 'Bank'; break;
                                                                case 'e_wallet': $method_label = 'E-Wallet'; break;
                                                                case 'qris': $method_label = 'QRIS'; $method_class = 'bg-info'; break;
                                                                case 'manual_admin': $method_label = 'Manual'; break;
                                                                default: $method_label = ucfirst($reg['metode_pembayaran']);
                                                            }
                                                        ?>
                                                        <span class="badge <?= $method_class ?> bg-opacity-10 text-white extra-small opacity-75" style="font-size: 0.65rem;">
                                                            <?= $method_label ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if(!empty($reg['bukti_bayar'])): ?>
                                                    <a href="../<?= htmlspecialchars($reg['bukti_bayar']); ?>" target="_blank" class="btn btn-outline-info extra-small py-1 px-2 rounded-2">
                                                        <i class="fas fa-receipt me-1"></i>Bukti Bayar
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="action-group">
                                                    <?php if($reg['status'] === 'diterima'): ?>
                                                        <a href="registrations.php?action=update_status&id=<?= $reg['id']; ?>&status=selesai" class="btn-action-pill btn-success" title="Tandai Lulus"><i class="fas fa-graduation-cap"></i></a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($reg['status'] !== 'diterima' && $reg['status'] !== 'selesai'): ?>
                                                        <a href="registrations.php?action=update_status&id=<?= $reg['id']; ?>&status=diterima" class="btn-action-pill btn-primary" title="Terima / Approve"><i class="fas fa-check"></i></a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($reg['status'] !== 'ditolak' && $reg['status'] !== 'selesai'): ?>
                                                        <a href="registrations.php?action=update_status&id=<?= $reg['id']; ?>&status=ditolak" class="btn-action-pill btn-danger" title="Tolak Peserta"><i class="fas fa-times"></i></a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($reg['status'] !== 'pending'): ?>
                                                        <a href="registrations.php?action=update_status&id=<?= $reg['id']; ?>&status=pending" class="btn-action-pill btn-secondary" title="Kembalikan ke Pending"><i class="fas fa-undo"></i></a>
                                                    <?php endif; ?>
                                                    <a href="registrations.php?action=delete&id=<?= $reg['id']; ?>" class="btn-action-pill btn-danger" title="Hapus Pendaftaran" onclick="return confirm('Yakin ingin menghapus data pendaftaran ini? Tindakan ini tidak dapat dibatalkan.')"><i class="fas fa-trash-alt"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada pendaftaran yang masuk.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
