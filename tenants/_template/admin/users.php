<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_GET['action'] ?? 'list';

// Handle Add User
if ($action === 'add_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';

    // Validation
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->execute([$email]);
    if ($stmt_check->fetch()) {
        $_SESSION['flash_error'] = "Email sudah terdaftar!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nama, $email, $no_hp, $hashed_password, $role])) {
            $_SESSION['flash_message'] = "Berhasil! Pengguna baru telah ditambahkan.";
        } else {
            $_SESSION['flash_error'] = "Gagal menambahkan pengguna baru.";
        }
    }
    header("Location: users.php");
    exit;
}

// Handle Delete User
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Prevent self-deletion
    if ($id != $_SESSION['user_id']) {
        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt_del->execute([$id])) {
            $_SESSION['flash_message'] = "Pengguna berhasil dihapus secara permanen.";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus pengguna.";
        }
    } else {
        $_SESSION['flash_error'] = "Anda tidak dapat menghapus akun Anda sendiri.";
    }
    header("Location: users.php");
    exit;
}

// Fetch all users
$stmt_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt_users->fetchAll();

// Generate user initials for avatar
function getInitials($name) {
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper(mb_substr($initials, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin Space</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">

            <?php if ($action === 'add'): ?>
                <!-- Header Add -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Tambah Pengguna Baru</h2>
                        <p class="text-muted mb-0">Cantumkan informasi akses untuk admin atau anggota baru.</p>
                    </div>
                </div>

                <div class="modern-card p-4">
                    <form action="users.php?action=add_process" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control form-control-modern" required placeholder="Jhon Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Alamat Email</label>
                                <input type="email" name="email" class="form-control form-control-modern" required placeholder="name@email.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Nomor WhatsApp</label>
                                <input type="text" name="no_hp" class="form-control form-control-modern" required placeholder="08...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Kata Sandi</label>
                                <input type="password" name="password" class="form-control form-control-modern" required placeholder="Min. 6 Karakter" minlength="6">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Hak Akses</label>
                                <select name="role" class="form-select form-control-modern">
                                    <option value="user">Peserta (User)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                        <hr class="my-5 opacity-10">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">Simpan Pengguna</button>
                            <a href="users.php" class="btn px-5 py-3 rounded-4 border-0" style="background: rgba(255,255,255,0.05); color: #fff;">Batal</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Header List -->
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <h2 class="fw-bold mb-1">Manajemen Pengguna</h2>
                        <p class="text-muted mb-0">Total <?= count($users); ?> pengguna terdaftar di sistem.</p>
                    </div>
                    <a href="users.php?action=add" class="btn btn-primary rounded-pill px-4 shadow-none">
                        <i class="fas fa-plus me-1"></i> Tambah Pengguna
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
                                    <th>Profil Pengguna</th>
                                    <th>Status Akses</th>
                                    <th>Kontak Peserta</th>
                                    <th class="text-end">Opsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($users) > 0): ?>
                                    <?php foreach($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="bg-primary bg-opacity-10 text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 0.8rem;">
                                                        <?= getInitials($u['nama']) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold small"><?= htmlspecialchars($u['nama']); ?></div>
                                                        <div class="extra-small text-muted">Join on <?= date('d/m/y', strtotime($u['created_at'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($u['role'] == 'admin'): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger extra-small rounded-pill"><i class="fas fa-user-shield me-1"></i>Administrator</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary extra-small rounded-pill"><i class="fas fa-user me-1"></i>Peserta</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="extra-small text-dark fw-bold mb-1"><?= htmlspecialchars($u['email']); ?></div>
                                                <div class="extra-small text-muted"><i class="fab fa-whatsapp text-success me-1"></i><?= htmlspecialchars($u['no_hp']); ?></div>
                                            </td>
                                            <td class="text-end">
                                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                                    <a href="users.php?action=delete&id=<?= $u['id']; ?>" class="btn btn-outline-danger btn-sm rounded-3 shadow-none" onclick="return confirm('Hapus pengguna ini secara permanen?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge border extra-small" style="background: rgba(255,255,255,0.05); color: #94A3B8;">Anda</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted">Tidak ada pengguna ditemukan.</td></tr>
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
