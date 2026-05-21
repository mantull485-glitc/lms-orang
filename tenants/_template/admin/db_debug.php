<?php
require_once __DIR__ . '/../config/database.php';

$output = "";
try {
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $output .= "Tables: " . implode(", ", $tables) . "\n\n";

    foreach ($tables as $table) {
        $output .= "Table: $table\n";
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= $create['Create Table'] . "\n\n";
    }

} catch (Exception $e) {
    $output .= "Error: " . $e->getMessage();
}

file_put_contents(__DIR__ . '/db_dump.txt', $output);
echo "Done";
?>
