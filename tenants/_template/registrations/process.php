<?php
session_start();
require_once '../config/tenant_guard.php';
require_once '../config/database.php';

// Only logged in users can register
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    $_SESSION['flash_error'] = "Admin tidak dapat mendaftar kelas.";
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_class'])) {
    $class_id = $_POST['class_id'];
    $user_id = $_SESSION['user_id'];

    // Validate if class exists
    $stmt_class = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt_class->execute([$class_id]);
    $class = $stmt_class->fetch();
    if (!$class) {
        $_SESSION['flash_error'] = "Kelas tidak ditemukan!";
        header("Location: ../classes/index.php");
        exit;
    }

    $final_price = ($class['harga_spesial'] !== null) ? $class['harga_spesial'] : $class['harga'];
    if ($final_price > 0) {
        // Redirection to payment gateway instead of auto-registering
        header("Location: payment.php?class_id=" . $class_id);
        exit;
    }

    // Check if already registered
    $stmt_check = $pdo->prepare("SELECT id FROM registrations WHERE user_id = ? AND class_id = ?");
    $stmt_check->execute([$user_id, $class_id]);
    
    if ($stmt_check->fetch()) {
        $_SESSION['flash_error'] = "Anda sudah terdaftar di kelas ini!";
    } else {
        // Register user to class
        try {
            $stmt = $pdo->prepare("INSERT INTO registrations (user_id, class_id, status, bukti_bayar) VALUES (?, ?, 'pending', NULL)");
            $stmt->execute([$user_id, $class_id]);
            $_SESSION['flash_message'] = "Pendaftaran berhasil! Status menunggu persetujuan admin.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Gagal mendaftar: " . $e->getMessage();
        }
    }

    header("Location: ../classes/detail.php?id=" . $class_id);
    exit;
}

header("Location: ../index.php");
exit;
?>
