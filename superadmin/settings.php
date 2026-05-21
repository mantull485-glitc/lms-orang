<?php
require_once 'auth_guard.php';

$flash = $_SESSION['flash_settings'] ?? null;
unset($_SESSION['flash_settings']);

// Load current admin data
$admin = $pdo_global->prepare("SELECT * FROM superadmins WHERE id=?");
$admin->execute([$_SESSION['superadmin_id']]);
$admin = $admin->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama  = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($nama) || empty($email)) {
            $flash = ['type'=>'danger','msg'=>'Nama dan email wajib diisi.'];
        } else {
            $pdo_global->prepare("UPDATE superadmins SET nama=?, email=? WHERE id=?")
                       ->execute([$nama, $email, $_SESSION['superadmin_id']]);
            $_SESSION['superadmin_nama'] = $nama;
            $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Profil berhasil diperbarui.'];
            header('Location: settings.php'); exit;
        }
    } elseif ($action === 'change_password') {
        $old = $_POST['password_lama'] ?? '';
        $new = $_POST['password_baru'] ?? '';
        $cnf = $_POST['password_konfirmasi'] ?? '';
        if (!password_verify($old, $admin['password'])) {
            $flash = ['type'=>'danger','msg'=>'Password lama tidak sesuai.'];
        } elseif (strlen($new) < 6) {
            $flash = ['type'=>'danger','msg'=>'Password baru minimal 6 karakter.'];
        } elseif ($new !== $cnf) {
            $flash = ['type'=>'danger','msg'=>'Konfirmasi password tidak cocok.'];
        } else {
            $pdo_global->prepare("UPDATE superadmins SET password=? WHERE id=?")
                       ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['superadmin_id']]);
            $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Password berhasil diubah.'];
            header('Location: settings.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan – Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sa-style.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="sa-main">
    <div class="sa-topbar">
        <div class="sa-topbar-title">Pengaturan Akun</div>
        <div class="sa-topbar-actions">
            <div class="sa-avatar"><?= strtoupper(substr($_SESSION['superadmin_nama'] ?? 'S', 0, 1)) ?></div>
        </div>
    </div>
    <div class="sa-content">

        <?php if ($flash): ?>
        <div class="sa-alert sa-alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="row g-4" style="max-width:760px">
            <!-- Update Profile -->
            <div class="col-12">
                <div class="sa-card">
                    <div class="sa-card-header">Informasi Profil</div>
                    <div class="sa-card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Nama Lengkap</label>
                                        <input type="text" name="nama" class="sa-form-control" required value="<?= htmlspecialchars($admin['nama']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Username</label>
                                        <input type="text" class="sa-form-control" value="<?= htmlspecialchars($admin['username']) ?>" disabled style="opacity:.5">
                                        <small style="color:var(--text-muted);font-size:.75rem">Username tidak dapat diubah</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="sa-form-control" required value="<?= htmlspecialchars($admin['email']) ?>">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn-sa-primary mt-2">Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-12">
                <div class="sa-card">
                    <div class="sa-card-header">Ganti Password</div>
                    <div class="sa-card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Password Lama</label>
                                        <input type="password" name="password_lama" class="sa-form-control" required placeholder="Masukkan password lama">
                                    </div>
                                </div>
                                <div class="col-md-6"></div>
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Password Baru</label>
                                        <input type="password" name="password_baru" class="sa-form-control" required placeholder="Min. 6 karakter">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="sa-form-group">
                                        <label>Konfirmasi Password Baru</label>
                                        <input type="password" name="password_konfirmasi" class="sa-form-control" required placeholder="Ulangi password baru">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn-sa-primary mt-2">Ganti Password</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="col-12">
                <div class="sa-card">
                    <div class="sa-card-header">Informasi Sistem</div>
                    <div class="sa-card-body">
                        <table style="width:100%;font-size:.88rem">
                            <?php
                            $total_t  = $pdo_global->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
                            $total_o  = $pdo_global->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                            $total_rev= $pdo_global->query("SELECT COALESCE(SUM(harga_bayar),0) FROM orders WHERE status='diterima'")->fetchColumn();
                            $rows = [
                                ['Total Tenant', $total_t . ' tenant'],
                                ['Total Order', $total_o . ' order'],
                                ['Total Revenue', 'Rp ' . number_format($total_rev, 0, ',', '.')],
                                ['PHP Version', PHP_VERSION],
                                ['Server Time', date('d M Y H:i:s')],
                                ['Login sebagai', $_SESSION['superadmin_user'] ?? '—'],
                            ];
                            foreach ($rows as [$k, $v]):
                            ?>
                            <tr>
                                <td style="color:var(--text-muted);padding:.4rem 0;width:160px"><?= $k ?></td>
                                <td style="color:var(--text);font-weight:500"><?= $v ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
