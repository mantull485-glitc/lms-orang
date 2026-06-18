<?php
$current_page = basename($_SERVER['PHP_SELF']);
if (!function_exists('getSetting')) require_once __DIR__ . '/../config/tenant_settings.php';
$_sidebar_nama = function_exists('getSetting') ? getSetting($pdo, 'nama_lembaga', 'Admin Panel') : 'Admin Panel';
$_sidebar_logo = function_exists('getSetting') ? getSetting($pdo, 'logo', '') : '';
$_sidebar_url  = function_exists('tenantUrl') ? tenantUrl() : '../index.php';
$_admin_nama   = htmlspecialchars($_SESSION['nama'] ?? 'Admin');
$_admin_initial = strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1));
?>
<div class="admin-sidebar d-flex flex-column">
    <!-- Header -->
    <div class="admin-sidebar-header">
        <div class="d-flex align-items-center gap-2">
            <?php if ($_sidebar_logo && file_exists(dirname(__DIR__).'/assets/img/'.$_sidebar_logo)): ?>
            <img src="../assets/img/<?= htmlspecialchars($_sidebar_logo) ?>" style="height:36px;width:auto;object-fit:contain;border-radius:8px" alt="">
            <?php else: ?>
            <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,106,0,0.12);border:1px solid rgba(255,106,0,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas fa-graduation-cap" style="color:#FF6A00;font-size:.95rem"></i>
            </div>
            <?php endif; ?>
            <div style="min-width:0">
                <div class="fw-bold text-white" style="font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px"><?= htmlspecialchars($_sidebar_nama) ?></div>
                <div class="extra-small" style="color:#94A3B8;display:flex;align-items:center;gap:4px;margin-top:1px">
                    <span style="width:6px;height:6px;border-radius:50%;background:#10B981;display:inline-block;box-shadow:0 0 6px #10B981"></span>
                    Administrator
                </div>
            </div>
        </div>
    </div>

    <!-- Nav label -->
    <div style="padding:.9rem 1.25rem .3rem">
        <div class="extra-small fw-bold text-uppercase ls-2" style="color:#475569">Menu Utama</div>
    </div>

    <!-- Nav links -->
    <ul class="nav flex-column mb-auto mt-1" style="padding:0 .5rem">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?= $current_page=='index.php'?'active':'' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="classes.php" class="nav-link <?= strpos($current_page,'classes.php')!==false?'active':'' ?>">
                <i class="fas fa-chalkboard"></i> Katalog Kelas
            </a>
        </li>
        <li class="nav-item">
            <a href="registrations.php" class="nav-link <?= $current_page=='registrations.php'?'active':'' ?>">
                <i class="fas fa-clipboard-list"></i> Data Pendaftaran
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= $current_page=='users.php'?'active':'' ?>">
                <i class="fas fa-users"></i> Daftar Peserta
            </a>
        </li>
        <li class="nav-item">
            <a href="certificates.php" class="nav-link <?= $current_page=='certificates.php'?'active':'' ?>">
                <i class="fas fa-award"></i> E-Sertifikat
            </a>
        </li>
        <li class="nav-item">
            <a href="team.php" class="nav-link <?= strpos($current_page,'team.php')!==false?'active':'' ?>">
                <i class="fas fa-user-tie"></i> Manajemen Tim
            </a>
        </li>
        <li class="nav-item">
            <a href="finance.php" class="nav-link <?= strpos($current_page,'finance.php')!==false?'active':'' ?>">
                <i class="fas fa-chart-line"></i> Keuangan
            </a>
        </li>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?= $current_page=='settings.php'?'active':'' ?>">
                <i class="fas fa-cog"></i> Pengaturan
            </a>
        </li>
        <li class="nav-item" style="margin-top:.5rem;padding:0 .25rem">
            <a href="<?= htmlspecialchars($_sidebar_url) ?>" target="_blank"
               class="nav-link"
               style="background:rgba(0,210,255,0.08);border:1px solid rgba(0,210,255,0.2);color:#00D2FF;border-radius:10px">
                <i class="fas fa-globe"></i> Kunjungi Beranda
            </a>
        </li>
    </ul>

    <!-- Footer user card -->
    <div class="sidebar-footer">
        <div style="background:rgba(255,106,0,0.08);border:1px solid rgba(255,106,0,0.15);border-radius:14px;padding:.9rem">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width:32px;height:32px;border-radius:9px;background:var(--primary-grad,linear-gradient(135deg,#FF6A00,#FF8800));display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:.75rem;flex-shrink:0">
                    <?= $_admin_initial ?>
                </div>
                <div style="min-width:0">
                    <div class="small fw-bold text-white text-truncate"><?= $_admin_nama ?></div>
                    <div class="extra-small" style="color:#94A3B8">Online</div>
                </div>
            </div>
            <a href="../auth/logout.php"
               style="display:flex;align-items:center;justify-content:center;gap:6px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#EF4444;border-radius:9px;padding:.4rem;font-size:.8rem;font-weight:600;text-decoration:none;transition:all .2s"
               onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                <i class="fas fa-sign-out-alt" style="font-size:.78rem"></i> Logout
            </a>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile Top Bar -->
<div class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-3">
    <button class="btn btn-link text-white p-1 border-0" id="sidebarToggleBtn" onclick="toggleSidebar()" style="text-decoration:none">
        <i class="fas fa-bars fs-5"></i>
    </button>
    <div class="d-flex align-items-center gap-2">
        <?php if ($_sidebar_logo && file_exists(dirname(__DIR__).'/assets/img/'.$_sidebar_logo)): ?>
        <img src="../assets/img/<?= htmlspecialchars($_sidebar_logo) ?>" style="height:26px;width:auto;object-fit:contain;border-radius:5px" alt="">
        <?php else: ?>
        <div style="width:26px;height:26px;border-radius:7px;background:rgba(255,106,0,0.15);display:flex;align-items:center;justify-content:center">
            <i class="fas fa-graduation-cap" style="color:#FF6A00;font-size:.7rem"></i>
        </div>
        <?php endif; ?>
        <span class="fw-bold text-white" style="font-size:.88rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($_sidebar_nama) ?></span>
    </div>
    <div style="width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#FF6A00,#FF8800);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:.65rem">
        <?= $_admin_initial ?>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('sidebar-open');
    overlay.classList.toggle('active');
    document.body.classList.toggle('sidebar-is-open');
}
function closeSidebar() {
    document.querySelector('.admin-sidebar').classList.remove('sidebar-open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.classList.remove('sidebar-is-open');
}
document.querySelectorAll('.admin-sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => { if (window.innerWidth < 992) closeSidebar(); });
});
</script>
