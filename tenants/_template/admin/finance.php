<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';
$tenant_id = $GLOBALS['tenant_id'] ?? 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ==========================================
// HANDLE PAYMENT VERIFICATION ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fin_action'], $_POST['reg_id'])) {
    $reg_id = intval($_POST['reg_id']);
    if ($_POST['fin_action'] === 'approve') {
        $pdo->prepare("UPDATE registrations SET status='diterima', tanggal_konfirmasi=NOW() WHERE id=? AND tenant_id=?")->execute([$reg_id, $tenant_id]);
        $_SESSION['flash_finance'] = ['type'=>'success','msg'=>'Pembayaran berhasil dikonfirmasi!'];
    } elseif ($_POST['fin_action'] === 'reject') {
        $catatan = htmlspecialchars(trim($_POST['catatan'] ?? ''));
        $pdo->prepare("UPDATE registrations SET status='ditolak', catatan_admin=? WHERE id=? AND tenant_id=?")->execute([$catatan, $reg_id, $tenant_id]);
        $_SESSION['flash_finance'] = ['type'=>'danger','msg'=>'Pembayaran telah ditolak.'];
    }
    $params_qs = [];
    if (!empty($_GET['bulan']))  $params_qs['bulan']  = $_GET['bulan'];
    if (!empty($_GET['tahun']))  $params_qs['tahun']  = $_GET['tahun'];
    if (!empty($_GET['status'])) $params_qs['status'] = $_GET['status'];
    $qs = http_build_query($params_qs);
    header("Location: finance.php" . ($qs ? "?$qs" : ''));
    exit;
}

// Filter parameters
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_status = $_GET['status'] ?? 'all';

// Build date range
$tgl_awal = "$filter_tahun-$filter_bulan-01";
$tgl_akhir = date('Y-m-t', strtotime($tgl_awal));

// ==========================================
// SUMMARY STATS
// ==========================================

// Total pendapatan bulan ini (status diterima/selesai)
$stmt_income = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE WHEN r.harga_saat_daftar > 0 THEN r.harga_saat_daftar
             WHEN c.harga_spesial IS NOT NULL THEN c.harga_spesial
             ELSE c.harga END
    ), 0) as total 
    FROM registrations r 
    JOIN classes c ON r.class_id = c.id
    WHERE r.tenant_id = ? AND r.status IN ('diterima','selesai') 
    AND EXTRACT(YEAR FROM r.tanggal_daftar) = ? AND EXTRACT(MONTH FROM r.tanggal_daftar) = ?
");
$stmt_income->execute([$tenant_id, (int)$filter_tahun, (int)$filter_bulan]);
$pendapatan_bulan = $stmt_income->fetchColumn();

// Total pendapatan all time
$st_all = $pdo->prepare("
    SELECT COALESCE(SUM(
        CASE WHEN r.harga_saat_daftar > 0 THEN r.harga_saat_daftar
             WHEN c.harga_spesial IS NOT NULL THEN c.harga_spesial
             ELSE c.harga END
    ), 0)
    FROM registrations r
    JOIN classes c ON r.class_id = c.id
    WHERE r.tenant_id = ? AND r.status IN ('diterima','selesai')
");
$st_all->execute([$tenant_id]);
$total_all = $st_all->fetchColumn();

// Jumlah transaksi bulan ini
$stmt_trx = $pdo->prepare("
    SELECT COUNT(*) FROM registrations 
    WHERE tenant_id = ? AND status IN ('diterima','selesai') 
    AND EXTRACT(YEAR FROM tanggal_daftar) = ? AND EXTRACT(MONTH FROM tanggal_daftar) = ?
");
$stmt_trx->execute([$tenant_id, (int)$filter_tahun, (int)$filter_bulan]);
$jumlah_trx = $stmt_trx->fetchColumn();

// Pending pembayaran
$st_pend = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE tenant_id = ? AND status = 'pending'");
$st_pend->execute([$tenant_id]);
$pending_count = $st_pend->fetchColumn();

// Total ditolak bulan ini
$stmt_ditolak = $pdo->prepare("
    SELECT COUNT(*) FROM registrations 
    WHERE tenant_id = ? AND status = 'ditolak'
    AND EXTRACT(YEAR FROM tanggal_daftar) = ? AND EXTRACT(MONTH FROM tanggal_daftar) = ?
");
$stmt_ditolak->execute([$tenant_id, (int)$filter_tahun, (int)$filter_bulan]);
$ditolak_bulan = $stmt_ditolak->fetchColumn();

// Pending payments WITH bukti_bayar (waiting for verification)
$stmt_pv = $pdo->prepare("
    SELECT r.id, r.tanggal_daftar, r.harga_saat_daftar, r.bukti_bayar,
           u.nama AS nama_peserta, u.email, u.no_hp,
           c.nama_kelas, c.kategori
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN classes c ON r.class_id = c.id
    WHERE r.tenant_id = ? AND r.status = 'pending' AND r.bukti_bayar IS NOT NULL AND r.bukti_bayar != ''
    ORDER BY r.tanggal_daftar ASC
");
$stmt_pv->execute([$tenant_id]);
$pending_verif = $stmt_pv->fetchAll();

// ==========================================
// TRANSACTIONS LIST (with filter)
// ==========================================
$statusFilter = '';
$params = [$tenant_id, $tgl_awal, $tgl_akhir];
if ($filter_status !== 'all') {
    $statusFilter = "AND r.status = ?";
    $params[] = $filter_status;
}

$stmt_list = $pdo->prepare("
    SELECT r.id, r.tanggal_daftar, r.harga_saat_daftar, r.status, r.metode_pembayaran,
           r.bukti_bayar, r.catatan_admin,
           u.nama AS nama_peserta, u.email, u.no_hp,
           c.nama_kelas, c.kategori, c.harga AS harga_kelas
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN classes c ON r.class_id = c.id
    WHERE r.tenant_id = ? AND DATE(r.tanggal_daftar) BETWEEN ? AND ?
    $statusFilter
    ORDER BY r.tanggal_daftar DESC
");
$stmt_list->execute($params);
$transactions = $stmt_list->fetchAll();

// ==========================================
// REVENUE PER KELAS (bulan ini)
// ==========================================
$stmt_per_kelas = $pdo->prepare("
    SELECT c.nama_kelas, c.kategori,
           COUNT(r.id) AS jumlah_peserta,
           COALESCE(SUM(CASE 
               WHEN r.status IN ('diterima','selesai') 
               THEN CASE WHEN r.harga_saat_daftar > 0 THEN r.harga_saat_daftar
                         WHEN c.harga_spesial IS NOT NULL THEN c.harga_spesial
                         ELSE c.harga END
               ELSE 0 END
           ), 0) AS total_pendapatan
    FROM classes c
    LEFT JOIN registrations r ON r.class_id = c.id AND r.tenant_id = ?
        AND EXTRACT(YEAR FROM r.tanggal_daftar) = ? AND EXTRACT(MONTH FROM r.tanggal_daftar) = ?
    WHERE c.tenant_id = ?
    GROUP BY c.id, c.nama_kelas, c.kategori
    ORDER BY total_pendapatan DESC
    LIMIT 8
");
$stmt_per_kelas->execute([$tenant_id, (int)$filter_tahun, (int)$filter_bulan, $tenant_id]);
$per_kelas = $stmt_per_kelas->fetchAll();

// ==========================================
// MONTHLY CHART DATA (12 months)
// ==========================================
$monthly_data = [];
for ($m = 1; $m <= 12; $m++) {
    $m_str = str_pad($m, 2, '0', STR_PAD_LEFT);
    $first = "$filter_tahun-$m_str-01";
    $last  = date('Y-m-t', strtotime($first));
    $stmt_m = $pdo->prepare("
        SELECT COALESCE(SUM(harga_saat_daftar),0) 
        FROM registrations 
        WHERE tenant_id = ? AND status IN ('diterima','selesai') 
        AND DATE(tanggal_daftar) BETWEEN ? AND ?
    ");
    $stmt_m->execute([$tenant_id, $first, $last]);
    $monthly_data[] = (int)$stmt_m->fetchColumn();
}

$tahun_list = range(date('Y') - 3, date('Y') + 1);
$bulan_names = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Admin LPK Lunarica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ====== Finance Page Specific Styles ====== */
        .fin-stat-card {
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.07);
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .fin-stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 20px 20px 0 0;
        }
        .fin-stat-card.green::before  { background: linear-gradient(90deg, #22c55e, #16a34a); }
        .fin-stat-card.blue::before   { background: linear-gradient(90deg, #3b82f6, #1d4ed8); }
        .fin-stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .fin-stat-card.red::before    { background: linear-gradient(90deg, #ef4444, #b91c1c); }
        .fin-stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.3); }
        .fin-stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }
        .fin-stat-value {
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 0.25rem;
        }
        .fin-stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        /* Filter bar */
        .filter-bar {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 1rem 1.25rem;
        }

        /* Table row hover */
        .table-trx tbody tr:hover td { background: rgba(99,102,241,0.06) !important; }

        /* Status badges */
        .badge-diterima  { background: rgba(34,197,94,.15);  color: #22c55e; }
        .badge-selesai   { background: rgba(59,130,246,.15); color: #60a5fa; }
        .badge-pending   { background: rgba(245,158,11,.15); color: #f59e0b; }
        .badge-ditolak   { background: rgba(239,68,68,.15);  color: #ef4444; }

        /* Chart card */
        .chart-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 1.5rem;
        }

        /* Per-class bar */
        .kelas-bar-wrap { display: flex; flex-direction: column; gap: 0.75rem; }
        .kelas-bar-item {}
        .kelas-bar-label { font-size: 0.78rem; font-weight: 600; }
        .kelas-bar-track {
            height: 8px; border-radius: 99px;
            background: rgba(255,255,255,0.08);
            margin-top: 4px;
        }
        .kelas-bar-fill {
            height: 100%; border-radius: 99px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transition: width 1s cubic-bezier(.4,0,.2,1);
        }

        /* Rupiah helper */
        .rp { font-family: 'Courier New', monospace; font-weight: 700; }

        .action-export {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.5rem 1.25rem;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: opacity .2s;
        }
        .action-export:hover { opacity: 0.85; }

        /* ====== Scoped dark content overrides ====== */
        /* Override Bootstrap CSS variables for dark area */
        .admin-content {
            --bs-body-color: #e2e8f0;
            --bs-body-bg: #0f172a;
            --bs-table-color: #e2e8f0;
            --bs-table-striped-color: #e2e8f0;
            --bs-heading-color: #f1f5f9;
            color: #e2e8f0;
            overflow-x: hidden;
        }
        .admin-content h1,.admin-content h2,.admin-content h3,
        .admin-content h4,.admin-content h5,.admin-content h6 { color: #f1f5f9 !important; }
        .admin-content p { color: #94a3b8 !important; }
        .admin-content .text-muted { color: #64748b !important; }
        .admin-content .text-white { color: #fff !important; }
        .admin-content .fw-bold { color: inherit; }
        .admin-content .small { color: inherit; }

        /* Cards inside dark area */
        .admin-content .modern-card {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid rgba(255,255,255,0.07) !important;
            overflow: hidden;
        }
        
        /* Tables inside dark area */
        .admin-content .table-modern,
        .admin-content .table { color: #e2e8f0 !important; }
        .admin-content .table-modern thead th,
        .admin-content .table > thead > tr > th { color: #94a3b8 !important; border-color: rgba(255,255,255,0.06) !important; background: rgba(255,255,255,0.04) !important; }
        .admin-content .table-modern tbody td,
        .admin-content .table > tbody > tr > td { color: #e2e8f0 !important; border-color: rgba(255,255,255,0.04) !important; background: transparent !important; }
        .admin-content .table-modern tbody tr:hover td,
        .admin-content .table > tbody > tr:hover > td { background: rgba(255,255,255,0.04) !important; }
        
        /* Form selects - white text idle, dark text when opened */
        .admin-content .form-select, .admin-content .form-control {
            color: #fff !important;
            background-color: rgba(255,255,255,0.06) !important;
            border-color: rgba(255,255,255,0.1) !important;
            transition: color 0.15s ease, background-color 0.15s ease;
        }
        .admin-content .form-select:focus,
        .admin-content .form-select:active {
            color: #1e293b !important;
            background-color: #fff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.25) !important;
        }
        .admin-content .form-select option {
            color: #1e293b;
            background-color: #fff;
        }


        /* Status badges in dark context */
        .admin-content .status-pending  { background: rgba(245,158,11,.2)  !important; color: #fbbf24 !important; }
        .admin-content .status-diterima { background: rgba(34,197,94,.2)   !important; color: #4ade80 !important; }
        .admin-content .status-selesai  { background: rgba(59,130,246,.2)  !important; color: #60a5fa !important; }
        .admin-content .status-ditolak  { background: rgba(239,68,68,.2)   !important; color: #f87171 !important; }

        /* Prevent layout bleed */
        .admin-content .container-fluid { max-width: 100%; overflow-x: hidden; }
        .admin-content .table-responsive { overflow-x: auto !important; }

    </style>
</head>
<body class="bg-light">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content" style="background:#0f172a; min-height:100vh; overflow-x:hidden;">
        <div class="container-fluid">

            <!-- ===== Header ===== -->
            <div class="d-flex justify-content-between align-items-start mb-5 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold mb-1">Laporan Keuangan</h2>
                    <p class="text-muted mb-0">Pantau pendapatan, transaksi, dan performa kelas secara real-time.</p>
                </div>
                <button class="action-export" onclick="exportCSV()">
                    <i class="fas fa-download me-2"></i>Export CSV
                </button>
            </div>

            <!-- ===== Filter Bar ===== -->
            <div class="filter-bar mb-5">
                <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                    <div class="col-auto">
                        <label class="form-label small fw-bold text-muted mb-1">Bulan</label>
                        <select name="bulan" class="form-select form-select-sm rounded-3" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.1);color:inherit;">
                            <?php for($i=1;$i<=12;$i++): ?>
                                <option value="<?= str_pad($i,2,'0',STR_PAD_LEFT) ?>" <?= $filter_bulan == str_pad($i,2,'0',STR_PAD_LEFT) ? 'selected':'' ?>>
                                    <?= $bulan_names[$i-1] ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small fw-bold text-muted mb-1">Tahun</label>
                        <select name="tahun" class="form-select form-select-sm rounded-3" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.1);color:inherit;">
                            <?php foreach($tahun_list as $y): ?>
                                <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected':'' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small fw-bold text-muted mb-1">Status Transaksi</label>
                        <select name="status" class="form-select form-select-sm rounded-3" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.1);color:inherit;">
                            <option value="all"       <?= $filter_status=='all'      ?'selected':''?>>Semua</option>
                            <option value="diterima"  <?= $filter_status=='diterima' ?'selected':''?>>Diterima</option>
                            <option value="selesai"   <?= $filter_status=='selesai'  ?'selected':''?>>Selesai</option>
                            <option value="pending"   <?= $filter_status=='pending'  ?'selected':''?>>Pending</option>
                            <option value="ditolak"   <?= $filter_status=='ditolak'  ?'selected':''?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm rounded-3 px-4">
                            <i class="fas fa-filter me-1"></i> Terapkan
                        </button>
                    </div>
                </form>
            </div>

            <!-- ===== Flash Message ===== -->
            <?php if(isset($_SESSION['flash_finance'])): $fl=$_SESSION['flash_finance']; unset($_SESSION['flash_finance']); ?>
            <div class="alert border-0 rounded-4 mb-4 small" style="background:rgba(<?= $fl['type']==='success'?'34,197,94':'239,68,68' ?>,.12);color:<?= $fl['type']==='success'?'#22c55e':'#f87171' ?>;">
                <i class="fas fa-<?= $fl['type']==='success'?'check-circle':'times-circle' ?> me-2"></i><?= $fl['msg'] ?>
            </div>
            <?php endif; ?>

            <!-- ===== Stat Cards ===== -->
            <div class="row g-4 mb-5">
                <div class="col-6 col-md-3">
                    <div class="fin-stat-card green">
                        <div class="fin-stat-icon" style="background:rgba(34,197,94,.12);color:#22c55e;"><i class="fas fa-coins"></i></div>
                        <div class="fin-stat-value text-white">Rp <?= number_format($pendapatan_bulan,0,',','.') ?></div>
                        <div class="fin-stat-label">Pendapatan Bulan Ini</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fin-stat-card blue">
                        <div class="fin-stat-icon" style="background:rgba(59,130,246,.12);color:#60a5fa;"><i class="fas fa-wallet"></i></div>
                        <div class="fin-stat-value text-white">Rp <?= number_format($total_all,0,',','.') ?></div>
                        <div class="fin-stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fin-stat-card orange">
                        <div class="fin-stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;"><i class="fas fa-receipt"></i></div>
                        <div class="fin-stat-value text-white"><?= number_format($jumlah_trx) ?></div>
                        <div class="fin-stat-label">Transaksi Lunas</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fin-stat-card red">
                        <div class="fin-stat-icon" style="background:rgba(239,68,68,.12);color:#f87171;"><i class="fas fa-hourglass-half"></i></div>
                        <div class="fin-stat-value text-white"><?= $pending_count ?></div>
                        <div class="fin-stat-label">Menunggu Verifikasi</div>
                    </div>
                </div>
            </div>

            <!-- ===== VERIFIKASI PEMBAYARAN PENDING ===== -->
            <?php if(count($pending_verif) > 0): ?>
            <div class="modern-card mb-5" style="border:1px solid rgba(245,158,11,.25);">
                <div class="p-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-bell text-warning me-2"></i>Verifikasi Pembayaran</h5>
                        <div class="extra-small text-muted mt-1"><?= count($pending_verif) ?> pembayaran menunggu konfirmasi admin</div>
                    </div>
                    <span class="badge rounded-pill px-3 py-2" style="background:rgba(245,158,11,.15);color:#f59e0b;font-size:.75rem;"><?= count($pending_verif) ?> Pending</span>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Kelas</th>
                                <th>Jumlah</th>
                                <th>Tanggal Daftar</th>
                                <th class="text-center">Bukti Bayar</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($pending_verif as $pv): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($pv['nama_peserta']) ?></div>
                                    <div class="extra-small text-muted"><?= htmlspecialchars($pv['email']) ?></div>
                                    <div class="extra-small text-muted"><?= htmlspecialchars($pv['no_hp']) ?></div>
                                </td>
                                <td>
                                    <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($pv['kategori']??'Umum') ?></div>
                                    <div class="small fw-bold text-truncate" style="max-width:180px;"><?= htmlspecialchars($pv['nama_kelas']) ?></div>
                                </td>
                                <td><span class="rp small text-white">Rp <?= number_format($pv['harga_saat_daftar'],0,',','.') ?></span></td>
                                <td class="extra-small text-muted"><?= date('d M Y, H:i', strtotime($pv['tanggal_daftar'])) ?></td>
                                <td class="text-center">
                                    <?php
                                    $bukti_ext = strtolower(pathinfo($pv['bukti_bayar'], PATHINFO_EXTENSION));
                                    $bukti_url = '../' . htmlspecialchars($pv['bukti_bayar']);
                                    ?>
                                    <?php if(in_array($bukti_ext,['jpg','jpeg','png'])): ?>
                                        <button type="button" class="btn btn-outline-info extra-small py-1 px-2 rounded-3"
                                            onclick="showProof('<?= $bukti_url ?>','<?= htmlspecialchars($pv['nama_peserta']) ?>')">
                                            <i class="fas fa-image me-1"></i>Lihat
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= $bukti_url ?>" target="_blank" class="btn btn-outline-info extra-small py-1 px-2 rounded-3">
                                            <i class="fas fa-file-pdf me-1"></i>Lihat PDF
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <!-- Approve -->
                                        <form method="POST" onsubmit="return confirm('Konfirmasi pembayaran ini?')">
                                            <input type="hidden" name="reg_id" value="<?= $pv['id'] ?>">
                                            <input type="hidden" name="fin_action" value="approve">
                                            <button type="submit" class="btn btn-sm rounded-3 fw-bold" style="background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.3);font-size:.72rem;">
                                                <i class="fas fa-check me-1"></i>Terima
                                            </button>
                                        </form>
                                        <!-- Reject -->
                                        <button type="button" class="btn btn-sm rounded-3 fw-bold" style="background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);font-size:.72rem;"
                                            onclick="showReject(<?= $pv['id'] ?>)">
                                            <i class="fas fa-times me-1"></i>Tolak
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== Chart + Per Kelas ===== -->
            <div class="row g-4 mb-5">
                <!-- Grafik Bulanan -->
                <div class="col-lg-8">
                    <div class="chart-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h6 class="fw-bold mb-0">Grafik Pendapatan <?= $filter_tahun ?></h6>
                                <div class="extra-small text-muted">Pendapatan per bulan (status diterima + selesai)</div>
                            </div>
                        </div>
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>

                <!-- Revenue Per Kelas -->
                <div class="col-lg-4">
                    <div class="chart-card h-100">
                        <h6 class="fw-bold mb-1">Top Kelas Bulan Ini</h6>
                        <div class="extra-small text-muted mb-4">Berdasarkan pendapatan</div>
                        <?php
                        $max_rev = count($per_kelas) > 0 ? max(array_column($per_kelas,'total_pendapatan')) : 1;
                        if ($max_rev == 0) $max_rev = 1;
                        ?>
                        <div class="kelas-bar-wrap">
                        <?php if(count($per_kelas) > 0): ?>
                            <?php foreach($per_kelas as $k): ?>
                                <div class="kelas-bar-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="kelas-bar-label text-truncate" style="max-width:150px;" title="<?= htmlspecialchars($k['nama_kelas']) ?>">
                                            <?= htmlspecialchars($k['nama_kelas']) ?>
                                        </div>
                                        <div class="extra-small rp text-white">Rp <?= number_format($k['total_pendapatan'],0,',','.') ?></div>
                                    </div>
                                    <div class="kelas-bar-track">
                                        <div class="kelas-bar-fill" style="width:<?= round(($k['total_pendapatan']/$max_rev)*100) ?>%"></div>
                                    </div>
                                    <div class="extra-small text-muted mt-1"><?= $k['jumlah_peserta'] ?> peserta</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">Belum ada data kelas.</div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== Transaction Table ===== -->
            <div class="modern-card mb-5">
                <div class="p-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0">Riwayat Transaksi</h5>
                        <div class="extra-small text-muted">
                            <?= $bulan_names[(int)$filter_bulan - 1] ?> <?= $filter_tahun ?> &mdash;
                            <span class="text-primary fw-bold"><?= count($transactions) ?> transaksi</span>
                        </div>
                    </div>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-modern table-trx align-middle" id="trxTable">
                        <thead>
                            <tr>
                                <th class="extra-small">#</th>
                                <th>Peserta</th>
                                <th>Kelas</th>
                                <th>Jumlah</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="text-center">Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(count($transactions) > 0): ?>
                            <?php foreach($transactions as $i => $t): ?>
                                <tr>
                                    <td class="extra-small text-muted"><?= $i+1 ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($t['nama_peserta']) ?></div>
                                        <div class="extra-small text-muted"><?= htmlspecialchars($t['email']) ?></div>
                                        <div class="extra-small text-muted"><?= htmlspecialchars($t['no_hp']) ?></div>
                                    </td>
                                    <td>
                                        <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($t['kategori'] ?? 'Umum') ?></div>
                                        <div class="small fw-bold text-truncate" style="max-width:180px;"><?= htmlspecialchars($t['nama_kelas']) ?></div>
                                    </td>
                                    <td>
                                        <span class="rp small text-white">
                                            Rp <?= number_format($t['harga_saat_daftar'],0,',','.') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if(!empty($t['metode_pembayaran'])): ?>
                                            <?php 
                                                $method_label = '';
                                                switch($t['metode_pembayaran']) {
                                                    case 'transfer_bank': $method_label = 'Bank Transfer'; break;
                                                    case 'e_wallet': $method_label = 'E-Wallet'; break;
                                                    case 'qris': $method_label = 'QRIS'; break;
                                                    case 'manual_admin': $method_label = 'Manual (Admin)'; break;
                                                    default: $method_label = ucfirst($t['metode_pembayaran']);
                                                }
                                            ?>
                                            <span class="badge bg-secondary bg-opacity-20 text-secondary extra-small">
                                                <?= $method_label ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="extra-small text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-premium status-<?= $t['status'] ?> extra-small rounded-pill px-3">
                                            <?= ucfirst($t['status']) ?>
                                        </span>
                                    </td>
                                    <td class="extra-small text-muted"><?= date('d M Y, H:i', strtotime($t['tanggal_daftar'])) ?></td>
                                    <td class="text-center">
                                        <?php if(!empty($t['bukti_bayar'])): ?>
                                            <a href="../<?= htmlspecialchars($t['bukti_bayar']) ?>" target="_blank"
                                               class="btn btn-outline-info extra-small py-1 px-2 rounded-3">
                                                <i class="fas fa-image me-1"></i>Lihat
                                            </a>
                                        <?php else: ?>
                                            <span class="extra-small text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3 d-block opacity-25"></i>
                                    Tidak ada transaksi pada periode ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /container-fluid -->
    </div><!-- /admin-content -->
</div><!-- /admin-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Proof Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:20px;">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold" id="proofModalLabel">Bukti Pembayaran</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-4">
        <img id="proofImg" src="" alt="Bukti" class="img-fluid rounded-3" style="max-height:70vh;">
      </div>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#0f172a;border:1px solid rgba(239,68,68,.25);border-radius:20px;">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Tolak Pembayaran</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <form method="POST" id="rejectForm">
          <input type="hidden" name="reg_id" id="rejectRegId">
          <input type="hidden" name="fin_action" value="reject">
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Alasan Penolakan (opsional)</label>
            <textarea name="catatan" class="form-control rounded-3 small" rows="3"
              style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.1);color:#fff;"
              placeholder="Misal: Bukti tidak jelas, nominal tidak sesuai..."></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm fw-bold rounded-3 flex-fill" style="background:rgba(239,68,68,.2);color:#f87171;border:1px solid rgba(239,68,68,.3);">Konfirmasi Penolakan</button>
            <button type="button" class="btn btn-sm rounded-3" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.06);color:#94a3b8;">Batal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
// ===== Proof Modal =====
function showProof(url, name) {
    document.getElementById('proofImg').src = url;
    document.getElementById('proofModalLabel').textContent = 'Bukti Bayar — ' + name;
    new bootstrap.Modal(document.getElementById('proofModal')).show();
}

// ===== Reject Modal =====
function showReject(regId) {
    document.getElementById('rejectRegId').value = regId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// ===== Revenue Chart =====
const ctx = document.getElementById('revenueChart').getContext('2d');
const monthlyData = <?= json_encode($monthly_data) ?>;
const monthLabels = <?= json_encode($bulan_names) ?>;

const gradient = ctx.createLinearGradient(0, 0, 0, 250);
gradient.addColorStop(0, 'rgba(99,102,241,0.35)');
gradient.addColorStop(1, 'rgba(99,102,241,0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: monthlyData,
            borderColor: '#6366f1',
            backgroundColor: gradient,
            borderWidth: 2.5,
            pointRadius: 5,
            pointBackgroundColor: '#6366f1',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID')
                },
                backgroundColor: 'rgba(20,20,35,0.95)',
                titleColor: '#fff',
                bodyColor: '#a5b4fc',
                padding: 12,
                borderColor: 'rgba(99,102,241,0.3)',
                borderWidth: 1
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: 'rgba(255,255,255,0.5)', font: { size: 11 } }
            },
            y: {
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: {
                    color: 'rgba(255,255,255,0.5)',
                    font: { size: 11 },
                    callback: v => 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : v.toLocaleString('id-ID'))
                }
            }
        }
    }
});

// ===== Export CSV =====
function exportCSV() {
    const table = document.getElementById('trxTable');
    if (!table) return;
    let csv = [];
    for (const row of table.rows) {
        let cells = [];
        for (const cell of row.cells) {
            let text = cell.innerText.replace(/\n/g,' ').replace(/,/g,';').trim();
            cells.push('"' + text + '"');
        }
        csv.push(cells.join(','));
    }
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'laporan_keuangan_<?= $filter_tahun ?>_<?= $filter_bulan ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>
