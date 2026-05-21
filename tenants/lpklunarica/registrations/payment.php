<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

// Only logged in users can register/pay
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    $_SESSION['flash_error'] = "Admin tidak dapat membeli kelas.";
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['class_id'])) {
    header("Location: ../classes/index.php");
    exit; 
}

$class_id = intval($_GET['class_id']);
$user_id = $_SESSION['user_id'];

// Check if class exists
$stmt_class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
$stmt_class->execute([$class_id]);
$class = $stmt_class->fetch();

if (!$class) {
    $_SESSION['flash_error'] = "Kelas tidak ditemukan!";
    header("Location: ../classes/index.php");
    exit;
}

// Calculate final price
$final_price = ($class['harga_spesial'] !== null) ? $class['harga_spesial'] : $class['harga'];

// If free, shouldn't be here
if ($final_price <= 0) {
    $_SESSION['flash_error'] = "Kelas ini gratis, harap daftar langsung.";
    header("Location: ../classes/detail.php?id=" . $class_id);
    exit;
}

// Check if already registered
$stmt_check = $pdo->prepare("SELECT id FROM registrations WHERE user_id = ? AND class_id = ?");
$stmt_check->execute([$user_id, $class_id]);
if ($stmt_check->fetch()) {
    $_SESSION['flash_error'] = "Anda sudah terdaftar di kelas ini!";
    header("Location: ../classes/detail.php?id=" . $class_id);
    exit;
}

// Handle Form Submission (Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bukti_bayar'])) {
    $file = $_FILES['bukti_bayar'];
    $method = $_POST['metode_pembayaran'] ?? 'transfer_bank';
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        $error = "Format file tidak didukung! Hanya JPG, PNG, dan PDF yang diperbolehkan.";
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = "Ukuran file terlalu besar! Maksimal 2MB.";
    } else {
        $upload_dir = '../uploads/payments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = 'payment_' . $user_id . '_' . $class_id . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        $db_filepath = 'uploads/payments/' . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO registrations (user_id, class_id, status, bukti_bayar, harga_saat_daftar, metode_pembayaran) VALUES (?, ?, 'pending', ?, ?, ?)");
                $stmt->execute([$user_id, $class_id, $db_filepath, $final_price, $method]);
                $_SESSION['flash_message'] = "Pendaftaran berhasil! Pembayaran sedang diverifikasi Admin.";
                header("Location: ../user/dashboard.php");
                exit;
            } catch (PDOException $e) {
                $error = "Gagal memproses pendaftaran: " . $e->getMessage();
            }
        } else {
            $error = "Terjadi kesalahan saat mengunggah bukti pembayaran.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selesaikan Pembayaran - LPK Lunarica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { padding-top: 65px; }

        .payment-card {
            background: rgba(30, 41, 59, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            overflow: hidden;
        }

        .payment-header {
            background: var(--primary-gradient);
            padding: 1.75rem 1.5rem;
            text-align: center;
        }

        .method-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 2rem;
        }

        .method-option {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .method-option.active {
            background: rgba(255, 106, 0, 0.1);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .method-option i {
            display: block;
            font-size: 1.2rem;
            margin-bottom: 6px;
        }

        .method-option span {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .instruction-box {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 1.5rem;
            display: none;
        }

        .instruction-box.active { display: block; }

        .amount-display {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -1px;
            color: #4ade80;
        }

        .file-upload-area {
            background: rgba(255,255,255,0.04);
            border: 2px dashed rgba(255,255,255,0.12);
            border-radius: 14px;
            padding: 1rem;
            transition: border-color 0.2s;
        }
        .file-upload-area:hover { border-color: var(--primary-color); }

        .btn-cancel {
            background: rgba(255,255,255,0.06);
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-cancel:hover { background: rgba(255,255,255,0.1); color: #cbd5e1; }

        @media (max-width: 575.98px) {
            .payment-card  { border-radius: 16px; }
            .amount-display { font-size: 1.7rem; }
            .payment-header { padding: 1.25rem 1rem; }
            .method-selector { gap: 6px; }
        }
    </style>
</head>
<body class="mesh-bg dark-theme">

<!-- Navbar -->
<nav class="navbar navbar-dark fixed-top"
     style="background: rgba(15,23,42,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.06);">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="../index.php">
            <div class="bg-primary rounded-2 d-flex align-items-center justify-content-center"
                 style="width:28px;height:28px;">
                <i class="fas fa-graduation-cap text-white" style="font-size:0.75rem;"></i>
            </div>
            <span>LPK Lunarica</span>
        </a>
        <a href="../classes/index.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i>
            <span class="d-none d-sm-inline">Kelas</span>
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6">

            <div class="payment-card">

                <!-- Header -->
                <div class="payment-header">
                    <div class="bg-white bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:52px;height:52px;">
                        <i class="fas fa-lock text-white fs-5"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-1">Selesaikan Pembayaran</h5>
                    <p class="text-white mb-0 extra-small opacity-75">
                        <?= htmlspecialchars($class['nama_kelas']); ?>
                    </p>
                </div>

                <!-- Body -->
                <div class="p-4 p-md-5">

                    <?php if (isset($error)): ?>
                        <div class="alert bg-danger bg-opacity-10 text-danger border-0 rounded-3 small mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>

                    <!-- Amount -->
                    <div class="text-center mb-4">
                        <div class="extra-small text-muted fw-bold text-uppercase mb-1"
                             style="letter-spacing:1px;">Total Tagihan</div>
                        <div class="amount-display">
                            Rp <?= number_format($final_price, 0, ',', '.'); ?>
                        </div>
                    </div>

                    <form action="payment.php?class_id=<?= $class_id ?>"
                          method="POST" enctype="multipart/form-data">
                        
                        <div class="extra-small text-muted fw-bold text-uppercase mb-2 text-center" style="letter-spacing:1px;">Pilih Metode Pembayaran</div>
                        
                        <div class="method-selector">
                            <div class="method-option active" data-method="transfer_bank">
                                <i class="fas fa-university"></i>
                                <span>Bank</span>
                            </div>
                            <div class="method-option" data-method="e_wallet">
                                <i class="fas fa-mobile-alt"></i>
                                <span>E-Wallet</span>
                            </div>
                            <div class="method-option" data-method="qris">
                                <i class="fas fa-qrcode"></i>
                                <span>QRIS</span>
                            </div>
                        </div>

                        <input type="hidden" name="metode_pembayaran" id="metode_input" value="transfer_bank">

                        <!-- Bank Instruction -->
                        <div id="instr_transfer_bank" class="instruction-box active mb-4">
                            <div class="text-center">
                                <div class="extra-small text-muted fw-bold mb-2">Transfer tepat sesuai nominal ke:</div>
                                <h5 class="fw-bold text-white mb-1">BCA - 1234567890</h5>
                                <p class="mb-0 extra-small text-muted">a.n LPK Lunarica Indonesia</p>
                            </div>
                        </div>

                        <!-- E-Wallet Instruction -->
                        <div id="instr_e_wallet" class="instruction-box mb-4">
                            <div class="text-center">
                                <div class="extra-small text-muted fw-bold mb-2">Transfer tepat sesuai nominal ke:</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 rounded-3 bg-white bg-opacity-5">
                                            <div class="extra-small text-muted fw-bold">DANA</div>
                                            <div class="small text-white">081234567890</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 rounded-3 bg-white bg-opacity-5">
                                            <div class="extra-small text-muted fw-bold">GOPAY</div>
                                            <div class="small text-white">081234567890</div>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 mb-0 extra-small text-muted">a.n LPK Lunarica Indonesia</p>
                            </div>
                        </div>

                        <!-- QRIS Instruction -->
                        <div id="instr_qris" class="instruction-box mb-4">
                            <div class="text-center">
                                <div class="extra-small text-muted fw-bold mb-3">Scan kode QR di bawah ini:</div>
                                <div class="bg-white p-2 rounded-3 d-inline-block mb-2">
                                    <img src="../assets/img/qris.png" alt="QRIS" class="img-fluid" style="max-width: 180px;">
                                </div>
                                <p class="mb-0 extra-small text-muted">Mendukung Dana, OVO, Gopay, LinkAja, dll.</p>
                            </div>
                        </div>

                        <!-- Upload Form -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">
                                Upload Bukti Transfer
                                <span class="text-danger">*</span>
                            </label>
                            <div class="file-upload-area">
                                <input type="file" name="bukti_bayar"
                                       class="form-control border-0 shadow-none bg-transparent text-white"
                                       accept=".jpg,.jpeg,.png,.pdf" required
                                       style="padding: 0.25rem 0;">
                            </div>
                            <div class="mt-2 extra-small text-muted">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Format JPG, PNG, atau PDF. Maks. 2MB.
                            </div>
                        </div>

                        <button type="submit"
                                class="btn btn-primary w-100 rounded-pill fw-bold py-3 mb-3 shadow-none">
                            <i class="fas fa-paper-plane me-2"></i>Konfirmasi Pembayaran
                        </button>

                        <a href="../classes/detail.php?id=<?= $class_id ?>"
                           class="btn btn-cancel w-100 rounded-pill py-2">
                            Batalkan &amp; Kembali
                        </a>
                    </form>

                    <div class="text-center mt-4">
                        <p class="extra-small text-muted mb-0">
                            <i class="fas fa-shield-alt me-1 text-success"></i>
                            Transaksi aman &mdash; Verifikasi maks 1&times;24 jam.
                        </p>
                    </div>

                </div><!-- /body -->
            </div><!-- /payment-card -->

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.method-option').forEach(opt => {
        opt.addEventListener('click', function() {
            // Remove active from all options
            document.querySelectorAll('.method-option').forEach(o => o.classList.remove('active'));
            // Add active to clicked
            this.classList.add('active');
            
            // Set hidden input value
            const method = this.getAttribute('data-method');
            document.getElementById('metode_input').value = method;
            
            // Hide all instructions
            document.querySelectorAll('.instruction-box').forEach(box => box.classList.remove('active'));
            // Show selected instruction
            document.getElementById('instr_' + method).classList.add('active');
        });
    });
</script>
</body>
</html>
