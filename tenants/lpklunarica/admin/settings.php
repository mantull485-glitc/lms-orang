<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
require_once '../config/tenant_settings.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit;
}

$flash = $_SESSION['flash_settings'] ?? null;
unset($_SESSION['flash_settings']);

// Load settings
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $s->execute([$key]);
    $r = $s->fetchColumn();
    return $r !== false ? $r : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $fields = ['nama_lembaga','tagline','alamat','no_telp','email_lembaga','website'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
                $upload_dir = __DIR__ . '/../assets/img/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . 'logo.' . $ext);
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('logo','logo.$ext') ON DUPLICATE KEY UPDATE setting_value='logo.$ext'")->execute();
            }
        }
        $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Informasi lembaga berhasil disimpan.'];
        header('Location: settings.php'); exit;

    } elseif ($action === 'update_payment') {
        $fields = ['nama_bank','no_rekening','nama_rekening','instruksi_bayar'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$f, $val, $val]);
        }
        $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Informasi pembayaran berhasil disimpan.'];
        header('Location: settings.php'); exit;

    } elseif ($action === 'update_tampilan') {
        // Simpan warna & setting tampilan
        $color_fields = ['color_primary','color_secondary','color_navy','color_navy_light'];
        foreach ($color_fields as $f) {
            $val = trim($_POST[$f] ?? '');
            // Validasi format hex warna
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
                $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                    ->execute([$f, $val, $val]);
            }
        }
        $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Tampilan & warna berhasil disimpan. Refresh halaman publik untuk melihat perubahan.'];
        header('Location: settings.php?tab=tampilan'); exit;

    } elseif ($action === 'reset_tampilan') {
        $defaults = [
            'color_primary'   => '#FF6A00',
            'color_secondary' => '#00D2FF',
            'color_navy'      => '#0F172A',
            'color_navy_light'=> '#1E293B',
        ];
        foreach ($defaults as $k => $v) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute([$k, $v, $v]);
        }
        $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Warna berhasil direset ke default.'];
        header('Location: settings.php?tab=tampilan'); exit;

    } elseif ($action === 'change_password') {
        $old = $_POST['password_lama'] ?? '';
        $new = $_POST['password_baru'] ?? '';
        $cnf = $_POST['password_konfirmasi'] ?? '';
        $user = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $user->execute([$_SESSION['user_id']]);
        $user = $user->fetch();
        if (!password_verify($old, $user['password'])) {
            $flash = ['type'=>'danger','msg'=>'Password lama tidak sesuai.'];
        } elseif (strlen($new) < 6) {
            $flash = ['type'=>'danger','msg'=>'Password baru minimal 6 karakter.'];
        } elseif ($new !== $cnf) {
            $flash = ['type'=>'danger','msg'=>'Konfirmasi password tidak cocok.'];
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $_SESSION['flash_settings'] = ['type'=>'success','msg'=>'Password berhasil diubah.'];
            header('Location: settings.php'); exit;
        }
    }
}

$s = [
    'nama_lembaga'    => getSetting($pdo, 'nama_lembaga'),
    'tagline'         => getSetting($pdo, 'tagline'),
    'alamat'          => getSetting($pdo, 'alamat'),
    'no_telp'         => getSetting($pdo, 'no_telp'),
    'email_lembaga'   => getSetting($pdo, 'email_lembaga'),
    'website'         => getSetting($pdo, 'website'),
    'nama_bank'       => getSetting($pdo, 'nama_bank'),
    'no_rekening'     => getSetting($pdo, 'no_rekening'),
    'nama_rekening'   => getSetting($pdo, 'nama_rekening'),
    'instruksi_bayar' => getSetting($pdo, 'instruksi_bayar'),
    'logo'            => getSetting($pdo, 'logo'),
    'color_primary'   => getSetting($pdo, 'color_primary',    '#FF6A00'),
    'color_secondary' => getSetting($pdo, 'color_secondary',  '#00D2FF'),
    'color_navy'      => getSetting($pdo, 'color_navy',       '#0F172A'),
    'color_navy_light'=> getSetting($pdo, 'color_navy_light', '#1E293B'),
];
$brand = getTenantBranding($pdo);
$active_tab = $_GET['tab'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan – Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php outputBrandingCSS($brand); ?>
</head>
<body class="mesh-bg dark-theme">
<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="admin-content">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Pengaturan Platform</h2>
                    <p class="text-muted mb-0">Kelola informasi lembaga dan konfigurasi platform Anda.</p>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?> border-0 shadow-sm rounded-4 mb-4">
                <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?> me-2"></i>
                <?= htmlspecialchars($flash['msg']) ?>
            </div>
            <?php endif; ?>

            <!-- Tab nav -->
            <ul class="nav nav-pills mb-4" id="settingsTabs">
                <li class="nav-item"><a class="nav-link <?= $active_tab==='info'?'active':'' ?>" href="?tab=info">Informasi Lembaga</a></li>
                <li class="nav-item"><a class="nav-link <?= $active_tab==='payment'?'active':'' ?>" href="?tab=payment">Pembayaran</a></li>
                <li class="nav-item"><a class="nav-link <?= $active_tab==='tampilan'?'active':'' ?>" href="?tab=tampilan"><i class="fas fa-palette me-1"></i>Tampilan & Warna</a></li>
                <li class="nav-item"><a class="nav-link <?= $active_tab==='password'?'active':'' ?>" href="?tab=password">Keamanan</a></li>
            </ul>

            <div class="tab-content">

                <!-- Tab: Info Lembaga -->
                <div class="<?= $active_tab==='info'?'d-block':'d-none' ?>" id="tab-info">
                    <div class="modern-card p-4" style="max-width:720px">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_info">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Logo Lembaga</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($s['logo']): ?>
                                        <img src="../assets/img/<?= htmlspecialchars($s['logo']) ?>" style="height:56px;width:auto;border-radius:8px;object-fit:contain;background:#fff;padding:4px" onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <input type="file" name="logo" class="form-control form-control-modern" accept=".jpg,.jpeg,.png,.webp,.svg" style="max-width:300px">
                                    </div>
                                    <small class="text-muted">JPG, PNG, SVG — Maks. 2MB</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Nama Lembaga / LPK</label>
                                    <input type="text" name="nama_lembaga" class="form-control form-control-modern" value="<?= htmlspecialchars($s['nama_lembaga']) ?>" placeholder="LPK Maju Bersama">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Tagline / Slogan</label>
                                    <input type="text" name="tagline" class="form-control form-control-modern" value="<?= htmlspecialchars($s['tagline']) ?>" placeholder="Platform Pelatihan Profesional">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control form-control-modern" rows="2" placeholder="Jl. Contoh No. 1, Kota"><?= htmlspecialchars($s['alamat']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">No. WhatsApp / Telp</label>
                                    <input type="text" name="no_telp" class="form-control form-control-modern" value="<?= htmlspecialchars($s['no_telp']) ?>" placeholder="08xxxxxxxxxx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Email Lembaga</label>
                                    <input type="email" name="email_lembaga" class="form-control form-control-modern" value="<?= htmlspecialchars($s['email_lembaga']) ?>" placeholder="info@lembaga.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Website (Opsional)</label>
                                    <input type="url" name="website" class="form-control form-control-modern" value="<?= htmlspecialchars($s['website']) ?>" placeholder="https://www.lembaga.com">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                    <i class="fas fa-save me-2"></i>Simpan Informasi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Pembayaran -->
                <div class="<?= $active_tab==='payment'?'d-block':'d-none' ?>" id="tab-payment">
                    <div class="modern-card p-4" style="max-width:720px">
                        <p class="text-muted small mb-4">Informasi rekening ini akan ditampilkan kepada siswa saat melakukan pendaftaran kelas.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_payment">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Nama Bank</label>
                                    <input type="text" name="nama_bank" class="form-control form-control-modern" value="<?= htmlspecialchars($s['nama_bank']) ?>" placeholder="BCA / Mandiri / BRI">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">No. Rekening</label>
                                    <input type="text" name="no_rekening" class="form-control form-control-modern" value="<?= htmlspecialchars($s['no_rekening']) ?>" placeholder="1234567890">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Atas Nama</label>
                                    <input type="text" name="nama_rekening" class="form-control form-control-modern" value="<?= htmlspecialchars($s['nama_rekening']) ?>" placeholder="Nama Pemilik Rekening">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Instruksi Pembayaran (Opsional)</label>
                                    <textarea name="instruksi_bayar" class="form-control form-control-modern" rows="3" placeholder="Contoh: Transfer sesuai nominal, lalu upload bukti pembayaran..."><?= htmlspecialchars($s['instruksi_bayar']) ?></textarea>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                    <i class="fas fa-save me-2"></i>Simpan Info Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Tampilan & Warna -->
                <div class="<?= $active_tab==='tampilan'?'d-block':'d-none' ?>" id="tab-tampilan">
                    <div class="modern-card p-4" style="max-width:760px">
                        <p class="text-muted small mb-4">Sesuaikan warna tema website Anda. Perubahan langsung diterapkan ke seluruh halaman publik.</p>

                        <!-- Live Preview Bar -->
                        <div id="preview-bar" class="rounded-4 p-4 mb-4" style="background:<?= $s['color_navy'] ?>;border:1px solid rgba(255,255,255,.1)">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div id="prev-brand" class="rounded-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;background:<?= $s['color_primary'] ?>">
                                    <i class="fas fa-graduation-cap text-white"></i>
                                </div>
                                <span id="prev-name" class="fw-bold text-white"><?= htmlspecialchars($s['nama_lembaga'] ?: 'Nama Lembaga') ?></span>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span id="prev-btn" class="px-4 py-2 rounded-pill text-white fw-bold" style="background:<?= $s['color_primary'] ?>;font-size:.85rem">Tombol Utama</span>
                                <span id="prev-btn2" class="px-4 py-2 rounded-pill fw-bold" style="border:1px solid <?= $s['color_primary'] ?>;color:<?= $s['color_primary'] ?>;font-size:.85rem">Tombol Outline</span>
                                <span id="prev-badge" class="px-3 py-1 rounded-pill text-white" style="background:<?= $s['color_secondary'] ?>;font-size:.8rem">Badge</span>
                            </div>
                            <div class="mt-3 small" style="color:rgba(255,255,255,.5)">Preview tampilan website Anda</div>
                        </div>

                        <form method="POST" id="form-tampilan">
                            <input type="hidden" name="action" value="update_tampilan">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Warna Utama (Primary)</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_primary" id="cp_primary" value="<?= htmlspecialchars($s['color_primary']) ?>" class="form-control form-control-color" style="width:56px;height:42px;border-radius:10px;cursor:pointer">
                                        <input type="text" id="cp_primary_hex" value="<?= htmlspecialchars($s['color_primary']) ?>" class="form-control form-control-modern" placeholder="#FF6A00" maxlength="7" style="max-width:120px">
                                    </div>
                                    <small class="text-muted">Warna tombol, link, aksen</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Warna Sekunder (Aksen)</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_secondary" id="cp_secondary" value="<?= htmlspecialchars($s['color_secondary']) ?>" class="form-control form-control-color" style="width:56px;height:42px;border-radius:10px;cursor:pointer">
                                        <input type="text" id="cp_secondary_hex" value="<?= htmlspecialchars($s['color_secondary']) ?>" class="form-control form-control-modern" placeholder="#00D2FF" maxlength="7" style="max-width:120px">
                                    </div>
                                    <small class="text-muted">Warna badge, glow efek</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Warna Latar Gelap (Navy)</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_navy" id="cp_navy" value="<?= htmlspecialchars($s['color_navy']) ?>" class="form-control form-control-color" style="width:56px;height:42px;border-radius:10px;cursor:pointer">
                                        <input type="text" id="cp_navy_hex" value="<?= htmlspecialchars($s['color_navy']) ?>" class="form-control form-control-modern" placeholder="#0F172A" maxlength="7" style="max-width:120px">
                                    </div>
                                    <small class="text-muted">Background utama halaman</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-muted">Warna Latar Card (Navy Light)</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_navy_light" id="cp_navy_light" value="<?= htmlspecialchars($s['color_navy_light']) ?>" class="form-control form-control-color" style="width:56px;height:42px;border-radius:10px;cursor:pointer">
                                        <input type="text" id="cp_navy_light_hex" value="<?= htmlspecialchars($s['color_navy_light']) ?>" class="form-control form-control-modern" placeholder="#1E293B" maxlength="7" style="max-width:120px">
                                    </div>
                                    <small class="text-muted">Background card & sidebar</small>
                                </div>
                            </div>

                            <!-- Preset Tema -->
                            <div class="mt-4">
                                <div class="fw-bold small text-muted mb-2">Preset Tema Cepat:</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm rounded-pill px-3" style="background:#FF6A00;color:#fff" onclick="applyPreset('#FF6A00','#00D2FF','#0F172A','#1E293B')">🔥 Orange (Default)</button>
                                    <button type="button" class="btn btn-sm rounded-pill px-3" style="background:#6366F1;color:#fff" onclick="applyPreset('#6366F1','#8B5CF6','#0F0F1A','#1E1B2E')">💜 Purple Dark</button>
                                    <button type="button" class="btn btn-sm rounded-pill px-3" style="background:#10B981;color:#fff" onclick="applyPreset('#10B981','#06B6D4','#0A1628','#162032')">🍃 Emerald</button>
                                    <button type="button" class="btn btn-sm rounded-pill px-3" style="background:#EF4444;color:#fff" onclick="applyPreset('#EF4444','#F97316','#1A0A0A','#2D1515')">🔴 Ruby Red</button>
                                    <button type="button" class="btn btn-sm rounded-pill px-3" style="background:#0EA5E9;color:#fff" onclick="applyPreset('#0EA5E9','#38BDF8','#0C1929','#172436')">💎 Sky Blue</button>
                                </div>
                            </div>

                            <div class="d-flex gap-3 mt-4">
                                <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                    <i class="fas fa-paint-brush me-2"></i>Simpan Tampilan
                                </button>
                                <button type="submit" name="action" value="reset_tampilan" class="btn btn-outline-secondary px-4 py-3 rounded-4"
                                    onclick="return confirm('Reset semua warna ke default?')">
                                    <i class="fas fa-undo me-2"></i>Reset Default
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab: Keamanan -->
                <div class="<?= $active_tab==='password'?'d-block':'d-none' ?>" id="tab-password">
                    <div class="modern-card p-4" style="max-width:480px">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Password Lama</label>
                                    <input type="password" name="password_lama" class="form-control form-control-modern" required placeholder="Masukkan password lama">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Password Baru</label>
                                    <input type="password" name="password_baru" class="form-control form-control-modern" required placeholder="Min. 6 karakter">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Konfirmasi Password Baru</label>
                                    <input type="password" name="password_konfirmasi" class="form-control form-control-modern" required placeholder="Ulangi password baru">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-5 py-3 rounded-4 shadow-none">
                                    <i class="fas fa-lock me-2"></i>Ganti Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sync color picker ↔ hex input + live preview
function syncColor(pickerId, hexId) {
    const picker = document.getElementById(pickerId);
    const hex    = document.getElementById(hexId);
    picker.addEventListener('input', () => {
        hex.value = picker.value;
        updatePreview();
    });
    hex.addEventListener('input', () => {
        if (/^#[0-9A-Fa-f]{6}$/.test(hex.value)) {
            picker.value = hex.value;
            updatePreview();
        }
    });
}
syncColor('cp_primary','cp_primary_hex');
syncColor('cp_secondary','cp_secondary_hex');
syncColor('cp_navy','cp_navy_hex');
syncColor('cp_navy_light','cp_navy_light_hex');

function updatePreview() {
    const primary   = document.getElementById('cp_primary').value;
    const secondary = document.getElementById('cp_secondary').value;
    const navy      = document.getElementById('cp_navy').value;
    document.getElementById('preview-bar').style.background = navy;
    document.getElementById('prev-brand').style.background  = primary;
    document.getElementById('prev-btn').style.background    = primary;
    document.getElementById('prev-btn2').style.borderColor  = primary;
    document.getElementById('prev-btn2').style.color        = primary;
    document.getElementById('prev-badge').style.background  = secondary;
}

function applyPreset(primary, secondary, navy, navyLight) {
    document.getElementById('cp_primary').value       = primary;
    document.getElementById('cp_primary_hex').value   = primary;
    document.getElementById('cp_secondary').value     = secondary;
    document.getElementById('cp_secondary_hex').value = secondary;
    document.getElementById('cp_navy').value          = navy;
    document.getElementById('cp_navy_hex').value      = navy;
    document.getElementById('cp_navy_light').value    = navyLight;
    document.getElementById('cp_navy_light_hex').value= navyLight;
    updatePreview();
}
</script>
