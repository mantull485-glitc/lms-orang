<?php
// Konfigurasi Database Tenant - Dynamic Loader (Vercel-compatible)
if (!function_exists('findPlatformRoot')) {
    function findPlatformRoot(): string {
        return dirname(dirname(dirname(__DIR__)));
    }
}

$platform_root = findPlatformRoot();
require_once $platform_root . '/config/superadmin_db.php';

// Detect subdomain from URL path
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$subdomain = '';
if (preg_match('/\/tenants\/([a-zA-Z0-9_-]+)/', $request_uri, $matches)) {
    $subdomain = $matches[1];
}

if ($subdomain === '_template' || empty($subdomain)) {
    // Development fallback or direct template access
    $dbname = 'lunarica_db'; 
} else {
    // Load database name from global database
    try {
        $stmt_t = $pdo_global->prepare("SELECT db_name FROM tenants WHERE subdomain = ?");
        $stmt_t->execute([$subdomain]);
        $t_rec = $stmt_t->fetch(PDO::FETCH_ASSOC);
        $dbname = $t_rec ? $t_rec['db_name'] : '';
    } catch (Exception $e) {
        $dbname = '';
    }
}

if (empty($dbname)) {
    die("Error: Tenant database not found for subdomain '" . htmlspecialchars($subdomain) . "'.");
}

$host = SA_DB_HOST;
$user = SA_DB_USER;
$pass = SA_DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if ($e->getCode() == 1049) { // 1049 is Unknown database
        try {
            $pdo_temp = new PDO("mysql:host=$host", $user, $pass);
            $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
            header("Location: init_db.php");
            exit;
        } catch (PDOException $e2) {
            die("Critical Database Error: " . $e2->getMessage());
        }
    }
    die("Database Connection failed. Error: " . $e->getMessage());
}
?>
