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

// Handle Upload/Update Certificate
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $class_id = intval($_POST['class_id']);
    $nomor_sertifikat = trim($_POST['nomor_sertifikat']);
    
    $stmt_check = $pdo->prepare("SELECT id, file_path FROM certificates WHERE user_id = ? AND class_id = ? AND tenant_id = ?");
    $stmt_check->execute([$user_id, $class_id, $tenant_id]);
    $existing = $stmt_check->fetch();

    if (isset($_FILES['file_sertifikat']) && $_FILES['file_sertifikat']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_sertifikat']['tmp_name'];
        $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file_sertifikat']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = time() . '_' . $user_id . '_' . $class_id . '_' . $file_name;
            $upload_dir = '../assets/certificates/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $target_file)) {
                $file_path = 'assets/certificates/' . $new_filename; 
                
                if ($existing) {
                    if (file_exists('../' . $existing['file_path'])) {
                        unlink('../' . $existing['file_path']);
                    }
                    $stmt = $pdo->prepare("UPDATE certificates SET nomor_sertifikat = ?, file_path = ? WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$nomor_sertifikat, $file_path, $existing['id'], $tenant_id]);
                    $_SESSION['flash_message'] = "Sertifikat berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO certificates (tenant_id, user_id, class_id, nomor_sertifikat, file_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$tenant_id, $user_id, $class_id, $nomor_sertifikat, $file_path]);
                    $_SESSION['flash_message'] = "Sertifikat berhasil diunggah.";
                }
            } else {
                $_SESSION['flash_error'] = "Gagal memindahkan file.";
            }
        } else {
            $_SESSION['flash_error'] = "Format file tidak didukung.";
        }
    } else {
        // If not uploading a file but updated number
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE certificates SET nomor_sertifikat = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$nomor_sertifikat, $existing['id'], $tenant_id]);
            $_SESSION['flash_message'] = "Nomor sertifikat berhasil diperbarui.";
        } else {
            $_SESSION['flash_error'] = "Gagal mengunggah file.";
        }
    }
    
    header("Location: certificates.php");
    exit;
}

// Handle Delete Certificate
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt_check = $pdo->prepare("SELECT file_path FROM certificates WHERE id = ? AND tenant_id = ?");
    $stmt_check->execute([$id, $tenant_id]);
    $cert = $stmt_check->fetch();
    
    if ($cert) {
        if (file_exists('../' . $cert['file_path'])) {
            unlink('../' . $cert['file_path']);
        }
        $stmt_del = $pdo->prepare("DELETE FROM certificates WHERE id = ? AND tenant_id = ?");
        $stmt_del->execute([$id, $tenant_id]);
        $_SESSION['flash_message'] = "Sertifikat berhasil dihapus.";
    }
    header("Location: certificates.php");
    exit;
}

// Fetch Records
$stmt_data = $pdo->prepare("
    SELECT r.id as reg_id, r.user_id, r.class_id, u.nama, u.email, c.nama_kelas, c.kategori,
           cert.id as cert_id, cert.nomor_sertifikat, cert.file_path, cert.created_at as cert_date
    FROM registrations r
    JOIN users u ON r.user_id = u.id AND u.tenant_id = r.tenant_id
    JOIN classes c ON r.class_id = c.id AND c.tenant_id = r.tenant_id
    LEFT JOIN certificates cert ON r.user_id = cert.user_id AND r.class_id = cert.class_id AND cert.tenant_id = r.tenant_id
    WHERE r.status = 'selesai' AND r.tenant_id = ?
    ORDER BY r.tanggal_daftar DESC
");
$stmt_data->execute([$tenant_id]);
$records = $stmt_data->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Kelola Sertifikat - Admin Space</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="mesh-bg dark-theme">

<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Manajemen E-Sertifikat</h2>
                    <p class="text-muted mb-0">Publikasi sertifikat kompetensi untuk para lulusan.</p>
                </div>
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

            <!-- Table Card -->
            <div class="modern-card">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Lulusan</th>
                                <th>Pelatihan</th>
                                <th>Status Sertifikat</th>
                                <th class="text-end">Opsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($records) > 0): ?>
                                <?php foreach($records as $rec): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold small text-dark"><?= htmlspecialchars($rec['nama']); ?></div>
                                            <div class="extra-small text-muted mt-1"><?= htmlspecialchars($rec['email']); ?></div>
                                        </td>
                                        <td>
                                            <div class="badge bg-primary bg-opacity-10 text-primary extra-small mb-1"><?= htmlspecialchars($rec['kategori'] ?? 'Umum'); ?></div>
                                            <div class="fw-bold small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($rec['nama_kelas']); ?></div>
                                        </td>
                                        <td>
                                            <?php if($rec['cert_id']): ?>
                                                <span class="badge badge-premium status-selesai extra-small">
                                                    <i class="fas fa-certificate me-1"></i>Terbit
                                                </span>
                                                <div class="extra-small text-muted mt-1 fw-bold"><?= htmlspecialchars($rec['nomor_sertifikat']); ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary extra-small">
                                                    Belum Terbit
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <?php if($rec['cert_id']): ?>
                                                    <a href="../<?= htmlspecialchars($rec['file_path']); ?>" target="_blank" class="btn btn-primary btn-sm rounded-3 shadow-none">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="certificates.php?action=delete&id=<?= $rec['cert_id']; ?>" class="btn btn-outline-danger btn-sm rounded-3 shadow-none" onclick="return confirm('Hapus sertifikat ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-light btn-sm rounded-3 fw-bold extra-small shadow-none" data-bs-toggle="modal" data-bs-target="#modal<?= $rec['reg_id']; ?>">
                                                    <i class="fas fa-<?= $rec['cert_id'] ? 'edit' : 'upload' ?> me-1"></i>
                                                    <?= $rec['cert_id'] ? 'Update' : 'Upload' ?>
                                                </button>
                                            </div>

                                            <!-- Modal -->
                                            <div class="modal fade" id="modal<?= $rec['reg_id']; ?>" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <form action="certificates.php?action=upload" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg p-2" style="border-radius: 24px;">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Penerbitan Sertifikat</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <input type="hidden" name="user_id" value="<?= $rec['user_id']; ?>">
                                                            <input type="hidden" name="class_id" value="<?= $rec['class_id']; ?>">
                                                            
                                                            <div class="bg-light rounded-4 p-3 mb-4">
                                                                <div class="extra-small text-muted fw-bold text-uppercase ls-1 mb-1">Nama Lulusan</div>
                                                                <div class="fw-bold small text-dark"><?= htmlspecialchars($rec['nama']); ?></div>
                                                                <hr class="my-2 opacity-10">
                                                                <div class="extra-small text-muted fw-bold text-uppercase ls-1 mb-1">Materi Pelatihan</div>
                                                                <div class="fw-bold small text-primary"><?= htmlspecialchars($rec['nama_kelas']); ?></div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small text-muted">Nomor Seri Sertifikat</label>
                                                                <input type="text" name="nomor_sertifikat" class="form-control form-control-modern" placeholder="LUN-2026-X123" value="<?= htmlspecialchars($rec['nomor_sertifikat'] ?? ''); ?>" required>
                                                            </div>
                                                            <div class="mb-2">
                                                                <label class="form-label fw-bold small text-muted">File Sertifikat (PDF/Image)</label>
                                                                <input type="file" name="file_sertifikat" class="form-control form-control-modern" accept=".pdf,.jpg,.jpeg,.png" <?= !$rec['cert_id'] ? 'required' : ''; ?>>
                                                            </div>
                                                            <div class="extra-small text-muted"><i class="fas fa-info-circle me-1"></i>Format yang didukung: PDF, JPG, PNG.</div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-4 shadow-none fw-bold">SIMPAN & PUBLIKASI</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">Belum ada peserta yang lulus pelatihan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
