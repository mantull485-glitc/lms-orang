<?php
require_once __DIR__ . '/config/superadmin_db.php';

try {
    $pdo_global->exec("TRUNCATE TABLE tenants CASCADE");
    $pdo_global->exec("TRUNCATE TABLE orders CASCADE");
    echo "SUCCESS: Semua data tenant, user tenant, order, kelas, dan transaksi berhasil dihapus dari database.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
