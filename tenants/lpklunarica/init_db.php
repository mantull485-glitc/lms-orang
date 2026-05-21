<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server without selecting a database first
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS lunarica_db");
    echo "Database created successfully or already exists.<br>";
    
    // Connect to the specific database
    $pdo->exec("USE lunarica_db");

    // Clear existing tables to apply new schema (since it's a fresh dev environment)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS certificates");
    $pdo->exec("DROP TABLE IF EXISTS registrations");
    $pdo->exec("DROP TABLE IF EXISTS classes");
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("DROP TABLE IF EXISTS settings");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 1. Create users table
    $createUsersTable = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            no_hp VARCHAR(20) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            avatar VARCHAR(255) DEFAULT 'assets/img/default-avatar.png',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createUsersTable);
    echo "Table 'users' created successfully.<br>";

    // 2. Create classes table
    $createClassesTable = "
        CREATE TABLE classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_kelas VARCHAR(100) NOT NULL,
            kategori VARCHAR(50) DEFAULT 'Umum',
            deskripsi TEXT,
            harga INT DEFAULT 0,
            harga_spesial INT DEFAULT NULL,
            jadwal DATETIME NOT NULL,
            link_zoom VARCHAR(255),
            status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createClassesTable);
    echo "Table 'classes' created successfully.<br>";

    // 3. Create registrations table
    $createRegistrationsTable = "
        CREATE TABLE registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            class_id INT NOT NULL,
            status ENUM('pending', 'diterima', 'ditolak', 'selesai') DEFAULT 'pending',
            bukti_bayar VARCHAR(255) NULL,
            harga_saat_daftar INT DEFAULT 0,
            metode_pembayaran VARCHAR(50) NULL,
            catatan_admin TEXT NULL,
            tanggal_daftar TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tanggal_konfirmasi DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_class (user_id, class_id)
        );
    ";
    $pdo->exec($createRegistrationsTable);
    echo "Table 'registrations' created successfully.<br>";

    // 4. Create certificates table
    $createCertificatesTable = "
        CREATE TABLE certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            class_id INT NOT NULL,
            nomor_sertifikat VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_class_cert (user_id, class_id)
        );
    ";
    $pdo->exec($createCertificatesTable);
    echo "Table 'certificates' created successfully.<br>";

    // 5. Create settings table
    $createSettingsTable = "
        CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createSettingsTable);
    echo "Table 'settings' created successfully.<br>";

    // 6. Create company_teams table
    $createTeamTable = "
        CREATE TABLE company_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(150) NOT NULL,
            jabatan VARCHAR(150) NOT NULL,
            deskripsi TEXT,
            foto VARCHAR(255)
        );
    ";
    $pdo->exec($createTeamTable);
    echo "Table 'company_teams' created successfully.<br>";

    // Insert Default Settings
    $settings = [
        ['site_name', 'LPK Lunarica'],
        ['site_email', 'info@lunarica.com'],
        ['site_phone', '081234567890'],
        ['site_address', 'Jl. Pendidikan No. 123, Jakarta'],
        ['bank_info', 'BCA - 1234567890 a.n LPK Lunarica Indonesia'],
        ['site_logo', 'assets/img/logo.png']
    ];
    $stmt_set = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $set) {
        $stmt_set->execute($set);
    }
    echo "Default settings inserted.<br>";

    // Insert Default Admin (Password: admin123)
    $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Administrator', 'admin@lunarica.com', '081234567890', $hashedPassword, 'admin']);
    echo "Default admin created successfully. (Email: admin@lunarica.com | Password: admin123)<br>";

    echo "<h3>Database Re-Initialization Complete with Enhanced Schema!</h3>";
    echo "<a href='index.php'>Go to Website</a>";

} catch(PDOException $e) {
    echo "DB Initialization Failed: " . $e->getMessage();
}
?>
