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

// Handle Add Class
if ($action === 'add_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_kelas']);
    $deskripsi = trim($_POST['deskripsi']);
    $jadwal = $_POST['jadwal'];
    $link = trim($_POST['link_zoom']);
    $kategori = trim($_POST['kategori']);
    $harga = isset($_POST['harga']) && $_POST['harga'] !== '' ? intval($_POST['harga']) : 0;
    $harga_spesial = isset($_POST['harga_spesial']) && $_POST['harga_spesial'] !== '' ? intval($_POST['harga_spesial']) : null;

    $stmt = $pdo->prepare("INSERT INTO classes (tenant_id, nama_kelas, deskripsi, harga, harga_spesial, jadwal, link_zoom, kategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$tenant_id, $nama, $deskripsi, $harga, $harga_spesial, $jadwal, $link, $kategori])) {
        $_SESSION['flash_message'] = "Kelas berhasil ditambahkan!";
    } else {
        $_SESSION['flash_error'] = "Gagal menambahkan kelas.";
    }
    header("Location: classes.php");
    exit;
}

// Handle Update Class
if ($action === 'edit_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nama = trim($_POST['nama_kelas']);
    $deskripsi = trim($_POST['deskripsi']);
    $jadwal = $_POST['jadwal'];
    $link = trim($_POST['link_zoom']);
    $kategori = trim($_POST['kategori']);
    $harga = isset($_POST['harga']) && $_POST['harga'] !== '' ? intval($_POST['harga']) : 0;
    $harga_spesial = isset($_POST['harga_spesial']) && $_POST['harga_spesial'] !== '' ? intval($_POST['harga_spesial']) : null;

    $stmt = $pdo->prepare("UPDATE classes SET nama_kelas=?, deskripsi=?, harga=?, harga_spesial=?, jadwal=?, link_zoom=?, kategori=? WHERE id=? AND tenant_id=?");
    if ($stmt->execute([$nama, $deskripsi, $harga, $harga_spesial, $jadwal, $link, $kategori, $id, $tenant_id])) {
        $_SESSION['flash_message'] = "Kelas berhasil diupdate!";
    } else {
        $_SESSION['flash_error'] = "Gagal mengupdate kelas.";
    }
    header("Location: classes.php");
    exit;
}

// Handle Delete Class
if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_GET['id'], $tenant_id]);
    $_SESSION['flash_message'] = "Kelas berhasil dihapus!";
    header("Location: classes.php");
    exit;
}

// Fetch classes
$stmt_classes = $pdo->prepare("SELECT * FROM classes WHERE tenant_id = ? ORDER BY jadwal ASC");
$stmt_classes->execute([$tenant_id]);
$classes = $stmt_classes->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manajemen Kelas - Admin Space</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">
            
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <?php
                    $is_edit = $action === 'edit';
                    $edit_class = null;
                    if ($is_edit && isset($_GET['id'])) {
                        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$_GET['id'], $tenant_id]);
                        $edit_class = $stmt->fetch();
                    }
                ?>
                <!-- Header Form -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1"><?= $is_edit ? 'Sunting Kelas' : 'Buka Kelas Baru' ?></h2>
                        <p class="text-muted mb-0">Isi detail pelatihan dengan lengkap dan teliti.</p>
                    </div>
                </div>

                <div class="modern-card p-4">
                    <form action="classes.php?action=<?= $is_edit ? 'edit_process' : 'add_process' ?>" method="POST">
                        <?php if($is_edit): ?>
                            <input type="hidden" name="id" value="<?= $edit_class['id']; ?>">
                        <?php endif; ?>

                        <div class="row g-4">
                            <div class="col-md-8">
                                <label class="form-label fw-bold small text-muted">Judul Pelatihan</label>
                                <input type="text" name="nama_kelas" class="form-control form-control-modern" 
                                    required placeholder="Cth: Web Development Dasar" 
                                    value="<?= $is_edit ? htmlspecialchars($edit_class['nama_kelas']) : '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Kategori</label>
                                <select name="kategori" class="form-select form-control-modern" required>
                                    <option value="Umum" <?= ($is_edit && $edit_class['kategori'] == 'Umum') ? 'selected' : '' ?>>Umum</option>
                                    <option value="Teknologi" <?= ($is_edit && $edit_class['kategori'] == 'Teknologi') ? 'selected' : '' ?>>Teknologi</option>
                                    <option value="Bisnis" <?= ($is_edit && $edit_class['kategori'] == 'Bisnis') ? 'selected' : '' ?>>Bisnis</option>
                                    <option value="Bahasa" <?= ($is_edit && $edit_class['kategori'] == 'Bahasa') ? 'selected' : '' ?>>Bahasa</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Deskripsi Pelatihan</label>
                                <textarea name="deskripsi" class="form-control form-control-modern" rows="5" required 
                                    placeholder="Jelaskan silabus dan target pembelajaran..."><?= $is_edit ? htmlspecialchars($edit_class['deskripsi']) : '' ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Harga Normal (Rp)</label>
                                <input type="number" name="harga" class="form-control form-control-modern" 
                                    required min="0" placeholder="0" 
                                    value="<?= $is_edit ? htmlspecialchars($edit_class['harga'] ?? 0) : '0' ?>">
                                <div class="extra-small text-muted mt-1">*Isi 0 jika kelas ini gratis.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Harga Promo / Diskon (Opsional)</label>
                                <input type="number" name="harga_spesial" class="form-control form-control-modern" 
                                    min="0" placeholder="Kosongkan jika tidak ada promo" 
                                    value="<?= $is_edit ? htmlspecialchars($edit_class['harga_spesial'] ?? '') : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Jadwal Mulai</label>
                                <input type="datetime-local" name="jadwal" class="form-control form-control-modern" 
                                    required value="<?= $is_edit ? date('Y-m-d\TH:i', strtotime($edit_class['jadwal'])) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Tautan Zoom (Opsional)</label>
                                <input type="url" name="link_zoom" class="form-control form-control-modern" 
                                    placeholder="https://zoom.us/j/..." 
                                    value="<?= $is_edit ? htmlspecialchars($edit_class['link_zoom'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <hr class="my-5 opacity-10">
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                <i class="fas fa-save me-2"></i><?= $is_edit ? 'Simpan Perubahan' : 'Terbitkan Kelas' ?>
                            </button>
                            <a href="classes.php" class="btn btn-light px-5 py-3 rounded-4 border-0">Batal</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Header List -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Manajemen Kelas</h2>
                        <p class="text-muted mb-0">Kelola kurikulum dan jadwal pelatihan aktif.</p>
                    </div>
                    <a href="classes.php?action=add" class="btn btn-primary rounded-pill px-4 shadow-none">
                        <i class="fas fa-plus me-1"></i> Buka Kelas Baru
                    </a>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
                    </div>
                <?php endif; ?>

                <div class="modern-card">
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>Pelatihan</th>
                                    <th>Jadwal & Biaya</th>
                                    <th>Peserta</th>
                                    <th class="text-end">Opsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($classes) > 0): ?>
                                    <?php foreach($classes as $c): ?>
                                        <?php 
                                            $p_stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE class_id = ? AND tenant_id = ? AND status = 'diterima'");
                                            $p_stmt->execute([$c['id'], $tenant_id]);
                                            $participants = $p_stmt->fetchColumn();
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($c['kategori'] ?? 'Umum'); ?></div>
                                                <div class="fw-bold small text-truncate" style="max-width:250px;"><?= htmlspecialchars($c['nama_kelas']); ?></div>
                                                <div class="extra-small text-muted mt-1"><?= !empty($c['link_zoom']) ? '<i class="fas fa-video text-success me-1"></i>Zoom Available' : '<i class="fas fa-home text-muted me-1"></i>Offline/Lainnya' ?></div>
                                            </td>
                                            <td>
                                                <div class="small fw-bold text-dark"><?= date('d/m/Y', strtotime($c['jadwal'])); ?> - <?= date('H:i', strtotime($c['jadwal'])); ?></div>
                                                <div class="mt-1">
                                                    <?php if($c['harga'] == 0): ?>
                                                        <span class="badge status-selesai extra-small">GRATIS</span>
                                                    <?php else: ?>
                                                        <?php if($c['harga_spesial'] !== null): ?>
                                                            <span class="extra-small text-muted text-decoration-line-through me-1">Rp<?= number_format($c['harga'], 0, ',', '.'); ?></span>
                                                            <span class="extra-small fw-bold text-primary">Rp<?= number_format($c['harga_spesial'], 0, ',', '.'); ?></span>
                                                        <?php else: ?>
                                                            <span class="extra-small fw-bold text-dark">Rp<?= number_format($c['harga'], 0, ',', '.'); ?></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-muted fw-bold rounded-pill border"><?= $participants; ?> Peserta</span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="classes.php?action=edit&id=<?= $c['id']; ?>" class="btn btn-light btn-sm rounded-3 shadow-none">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="classes.php?action=delete&id=<?= $c['id']; ?>" class="btn btn-outline-danger btn-sm rounded-3 shadow-none" onclick="return confirm('Hapus kelas ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada kelas pelatihan.</td></tr>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
