<?php
// Database setup script for JJ Reporting Dashboard
// This script creates the necessary database tables including the missing admins table

require_once 'config.php';

echo "<h2>JJ Reporting Dashboard - Database Setup</h2>";

try {
    // Connect to MySQL server (without specifying database)
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✓ Database '" . DB_NAME . "' created or verified</p>";
    
    // Select the database
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Create admins table
    $createAdminsTable = "
    CREATE TABLE IF NOT EXISTS `admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `username` varchar(100) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `email` varchar(255) DEFAULT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createAdminsTable);
    echo "<p>✓ Admins table created</p>";
    
    // Insert default admin user
    $checkAdmin = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
    $checkAdmin->execute(['super_admin']);
    
    if ($checkAdmin->fetchColumn() == 0) {
        $insertAdmin = $pdo->prepare("INSERT INTO admins (name, username, password, email, is_active) VALUES (?, ?, ?, ?, ?)");
        $insertAdmin->execute([
            'Super Administrator',
            'super_admin',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin@company.com',
            1
        ]);
        echo "<p>✓ Default admin user created</p>";
        echo "<p><strong>Default Login Credentials:</strong></p>";
        echo "<p>Username: <code>super_admin</code></p>";
        echo "<p>Password: <code>admin123</code></p>";
    } else {
        echo "<p>✓ Admin user already exists</p>";
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>You can now <a href='index.php'>go to the login page</a> and use the credentials above.</p>";
    echo "<p><strong>Important:</strong> Change the default password after your first login!</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
}
?>

