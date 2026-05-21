<?php
require_once 'config/database.php';
$tenant_id = $GLOBALS['tenant_id'] ?? 0;
$stmt = $pdo->prepare("SELECT id, status, LENGTH(status) as len, user_id FROM registrations WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
