<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT id, status, LENGTH(status) as len, user_id FROM registrations");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
