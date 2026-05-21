<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$action = $_GET['action'] ?? 'list';

// Handle Add Team Member
if ($action === 'add_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama']);
    $jabatan = trim($_POST['jabatan']);
    $deskripsi = trim($_POST['deskripsi']);
    $foto_path = '';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $filename = uniqid('team_') . '.' . $ext;
            $destination = '../uploads/team/' . $filename;
            // Create uploads directory if not exist
            if (!is_dir('../uploads/team/')) {
                @mkdir('../uploads/team/', 0755, true);
            }
            if (@move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                $foto_path = 'uploads/team/' . $filename;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO team (tenant_id, nama, jabatan, bio, foto) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$tenant_id, $nama, $jabatan, $deskripsi, $foto_path])) {
        $_SESSION['flash_message'] = "Anggota tim berhasil ditambahkan!";
    } else {
        $_SESSION['flash_error'] = "Gagal menambahkan anggota tim.";
    }
    header("Location: team.php");
    exit;
}

// Handle Update Team Member
if ($action === 'edit_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nama = trim($_POST['nama']);
    $jabatan = trim($_POST['jabatan']);
    $deskripsi = trim($_POST['deskripsi']);
    
    // Check old photo
    $stmt_old = $pdo->prepare("SELECT foto FROM team WHERE id = ? AND tenant_id = ?");
    $stmt_old->execute([$id, $tenant_id]);
    $old_data = $stmt_old->fetch();
    $foto_path = $old_data['foto'] ?? '';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $filename = uniqid('team_') . '.' . $ext;
            $destination = '../uploads/team/' . $filename;
            // Create uploads directory if not exist
            if (!is_dir('../uploads/team/')) {
                @mkdir('../uploads/team/', 0755, true);
            }
            if (@move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
                // Delete old file if exists
                if (!empty($foto_path) && file_exists('../' . $foto_path)) {
                    @unlink('../' . $foto_path);
                }
                $foto_path = 'uploads/team/' . $filename;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE team SET nama=?, jabatan=?, bio=?, foto=? WHERE id=? AND tenant_id=?");
    if ($stmt->execute([$nama, $jabatan, $deskripsi, $foto_path, $id, $tenant_id])) {
        $_SESSION['flash_message'] = "Data anggota berhasil diupdate!";
    } else {
        $_SESSION['flash_error'] = "Gagal mengupdate data anggota.";
    }
    header("Location: team.php");
    exit;
}

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $stmt_old = $pdo->prepare("SELECT foto FROM team WHERE id = ? AND tenant_id = ?");
    $stmt_old->execute([$_GET['id'], $tenant_id]);
    $old_data = $stmt_old->fetch();
    
    if (!empty($old_data['foto']) && file_exists('../' . $old_data['foto'])) {
        @unlink('../' . $old_data['foto']);
    }

    $stmt = $pdo->prepare("DELETE FROM team WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_GET['id'], $tenant_id]);
    $_SESSION['flash_message'] = "Anggota tim berhasil dihapus!";
    header("Location: team.php");
    exit;
}

// Fetch team members
$stmt_team = $pdo->prepare("SELECT *, bio AS deskripsi FROM team WHERE tenant_id = ? ORDER BY urutan ASC, id DESC");
$stmt_team->execute([$tenant_id]);
$company_teams = $stmt_team->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tim - Admin Space</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .team-img-preview {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--primary-light);
        }
    </style>
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">
            
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <?php
                    $is_edit = $action === 'edit';
                    $edit_member = null;
                    if ($is_edit && isset($_GET['id'])) {
                        $stmt = $pdo->prepare("SELECT *, bio AS deskripsi FROM team WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$_GET['id'], $tenant_id]);
                        $edit_member = $stmt->fetch();
                    }
                ?>
                <!-- Header Form -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1"><?= $is_edit ? 'Sunting Anggota Tim' : 'Tambah Anggota Baru' ?></h2>
                        <p class="text-muted mb-0">Isi detail profil anggota tim yang akan ditampilkan di halaman Tentang Kami.</p>
                    </div>
                </div>

                <div class="modern-card p-4">
                    <form action="team.php?action=<?= $is_edit ? 'edit_process' : 'add_process' ?>" method="POST" enctype="multipart/form-data">
                        <?php if($is_edit): ?>
                            <input type="hidden" name="id" value="<?= $edit_member['id']; ?>">
                        <?php endif; ?>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control form-control-modern" 
                                    required placeholder="Cth: Sarah Wijaya" 
                                    value="<?= $is_edit ? htmlspecialchars($edit_member['nama']) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Jabatan / Peran</label>
                                <input type="text" name="jabatan" class="form-control form-control-modern" 
                                    required placeholder="Cth: Chief Executive Officer" 
                                    value="<?= $is_edit ? htmlspecialchars($edit_member['jabatan']) : '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Deskripsi Singkat (Opsional)</label>
                                <textarea name="deskripsi" class="form-control form-control-modern" rows="3" 
                                    placeholder="Ceritakan singkat tentang peran orang ini..."><?= $is_edit ? htmlspecialchars($edit_member['deskripsi']) : '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">Foto Profil (JPG/PNG/WEBP)</label>
                                <input type="file" name="foto" class="form-control form-control-modern" accept="image/*" <?= !$is_edit ? 'required' : '' ?>>
                                <div class="extra-small text-muted mt-1">*Disarankan resolusi rasio 1:1 (persegi) agar terlihat rapi.</div>
                                <?php if($is_edit && !empty($edit_member['foto'])): ?>
                                    <div class="mt-3">
                                        <p class="small text-muted mb-2">Foto saat ini:</p>
                                        <img src="../<?= htmlspecialchars($edit_member['foto']); ?>" class="team-img-preview" alt="Preview">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr class="my-5 opacity-10">
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                <i class="fas fa-save me-2"></i><?= $is_edit ? 'Simpan Perubahan' : 'Tambahkan Anggota' ?>
                            </button>
                            <a href="team.php" class="btn px-5 py-3 rounded-4 border-0" style="background: rgba(255,255,255,0.05); color: #fff;">Batal</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Header List -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Manajemen Tim</h2>
                        <p class="text-muted mb-0">Kelola profil anggota tim untuk halaman Tentang Kami.</p>
                    </div>
                    <a href="team.php?action=add" class="btn btn-primary rounded-pill px-4 shadow-none">
                        <i class="fas fa-plus me-1"></i> Tambah Anggota
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
                                    <th width="80">Foto</th>
                                    <th>Identitas Pegawai</th>
                                    <th>Jabatan</th>
                                    <th class="text-end">Opsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($company_teams) > 0): ?>
                                    <?php foreach($company_teams as $member): ?>
                                        <tr>
                                            <td>
                                                <?php if(!empty($member['foto']) && file_exists('../' . $member['foto'])): ?>
                                                    <img src="../<?= htmlspecialchars($member['foto']); ?>" alt="<?= htmlspecialchars($member['nama']); ?>" class="rounded-circle object-fit-cover" style="width: 50px; height: 50px; border: 2px solid var(--primary-color);">
                                                <?php else: ?>
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 50px; height: 50px;">
                                                        <?= strtoupper(substr($member['nama'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold small"><?= htmlspecialchars($member['nama']); ?></div>
                                                <?php if(!empty($member['deskripsi'])): ?>
                                                    <div class="extra-small text-muted mt-1 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($member['deskripsi']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge border rounded-pill px-3 py-2" style="background: rgba(255,255,255,0.05); color: #fff;"><?= htmlspecialchars($member['jabatan']); ?></span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="team.php?action=edit&id=<?= $member['id']; ?>" class="btn btn-sm rounded-3 shadow-none text-white" style="background: rgba(255,255,255,0.1);">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="team.php?action=delete&id=<?= $member['id']; ?>" class="btn btn-outline-danger btn-sm rounded-3 shadow-none" onclick="return confirm('Hapus profil <?= htmlspecialchars($member['nama']); ?>?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada profil tim yang ditambahkan.</td></tr>
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
