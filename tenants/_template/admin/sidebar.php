<?php
$current_page = basename($_SERVER['PHP_SELF']);
// Load tenant name for sidebar (database.php already included by parent)
if (!function_exists('getSetting')) require_once __DIR__ . '/../config/tenant_settings.php';
$_sidebar_nama   = function_exists('getSetting') ? getSetting($pdo, 'nama_lembaga', 'Admin Panel') : 'Admin Panel';
$_sidebar_logo   = function_exists('getSetting') ? getSetting($pdo, 'logo', '') : '';
$_sidebar_url    = function_exists('tenantUrl') ? tenantUrl() : '../index.php';
?>
<div class="admin-sidebar d-flex flex-column">
    <div class="admin-sidebar-header">
        <div class="d-flex align-items-center gap-2">
            <?php if ($_sidebar_logo && file_exists(dirname(__DIR__).'/assets/img/'.$_sidebar_logo)): ?>
            <img src="../assets/img/<?= htmlspecialchars($_sidebar_logo) ?>" style="height:36px;width:auto;object-fit:contain;border-radius:6px" alt="">
            <?php else: ?>
            <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="fas fa-graduation-cap text-primary fs-5"></i>
            </div>
            <?php endif; ?>
            <div>
                <h5 class="fw-bold mb-0 text-white" style="font-size:.9rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($_sidebar_nama) ?></h5>
                <div class="extra-small text-muted-light"><i class="fas fa-circle text-success me-1" style="font-size: 0.5rem;"></i>Administrator</div>
            </div>
        </div>
    </div>
    
    <div class="mt-4 px-3 mb-2">
        <div class="extra-small fw-bold text-muted-light text-uppercase ls-2">Menu Utama</div>
    </div>
    
    <ul class="nav flex-column mb-auto mt-2">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="classes.php" class="nav-link <?= strpos($current_page, 'classes.php') !== false ? 'active' : '' ?>">
                <i class="fas fa-chalkboard me-2"></i> Katalog Kelas
            </a>
        </li>
        <li class="nav-item">
            <a href="registrations.php" class="nav-link <?= $current_page == 'registrations.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list me-2"></i> Data Pendaftaran
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users me-2"></i> Daftar Peserta
            </a>
        </li>
        <li class="nav-item">
            <a href="certificates.php" class="nav-link <?= $current_page == 'certificates.php' ? 'active' : '' ?>">
                <i class="fas fa-award me-2"></i> E-Sertifikat
            </a>
        </li>
        <li class="nav-item">
            <a href="team.php" class="nav-link <?= strpos($current_page, 'team.php') !== false ? 'active' : '' ?>">
                <i class="fas fa-user-tie me-2"></i> Manajemen Tim
            </a>
        </li>
        <li class="nav-item">
            <a href="finance.php" class="nav-link <?= strpos($current_page, 'finance.php') !== false ? 'active' : '' ?>">
                <i class="fas fa-chart-line me-2"></i> Keuangan
            </a>
        </li>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog me-2"></i> Pengaturan
            </a>
        </li>
        <li class="nav-item mt-3">
            <a href="<?= htmlspecialchars($_sidebar_url) ?>" target="_blank" class="nav-link text-info bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3">
                <i class="fas fa-globe me-2"></i> Kunjungi Beranda
            </a>
        </li>
    </ul>

    <div class="sidebar-footer mt-auto p-4">
        <div class="bg-primary bg-opacity-10 p-3 rounded-4 mb-3 border border-primary border-opacity-10">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.7rem;">
                    <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="text-truncate">
                    <div class="small fw-bold text-white"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></div>
                    <div class="extra-small text-muted-light">Online</div>
                </div>
            </div>
            <a href="../auth/logout.php" class="btn btn-white btn-sm w-100 rounded-pill border-0 fw-bold text-primary shadow-sm mt-2">
                <i class="fas fa-sign-out-alt me-2 small"></i>Logout
            </a>
        </div>
        <a href="<?= htmlspecialchars($_sidebar_url) ?>" target="_blank" class="btn btn-link btn-sm w-100 text-muted-light text-decoration-none extra-small">
            <i class="fas fa-external-link-alt me-1"></i>Kunjungi Situs
        </a>
    </div>
</div>

<!-- Mobile Sidebar Overlay (backdrop) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Mobile Top Bar (only visible on small screens) -->
<div class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-3">
    <button class="btn btn-link text-white p-1" id="sidebarToggleBtn" onclick="toggleSidebar()">
        <i class="fas fa-bars fs-5"></i>
    </button>
    <div class="d-flex align-items-center gap-2">
        <div class="bg-primary bg-opacity-10 rounded-2 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
            <i class="fas fa-rocket text-primary" style="font-size: 0.75rem;"></i>
        </div>
        <span class="fw-bold text-white" style="font-size: 0.9rem;"><?= htmlspecialchars($_sidebar_nama) ?> Admin</span>
    </div>
    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; font-size: 0.65rem;">
        <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)); ?>
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
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('sidebar-open');
    overlay.classList.remove('active');
    document.body.classList.remove('sidebar-is-open');
}
// Close sidebar when nav link is clicked on mobile
document.querySelectorAll('.admin-sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) closeSidebar();
    });
});
</script>
