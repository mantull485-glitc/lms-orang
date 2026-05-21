<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'lunarica_db';

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
            header("Location: /lpk_lunarica/init_db.php");
            exit;
        } catch (PDOException $e2) {
            die("Critical Database Error: " . $e2->getMessage());
        }
    }
    die("Database Connection failed. Error: " . $e->getMessage());
}
?>
