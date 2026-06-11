<?php
include 'config.php';

// Check if running from CLI or authorized
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1)) {
    die("Unauthorized");
}

echo "Starting Migration: Fix User Integrity...\n";

function safeAddConstraint($conn, $table, $col, $refTable, $refCol, $constraintName) {
    echo "Processing $table ($col)... ";
    
    // 1. Check if constraint already exists
    $check = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$table' 
        AND COLUMN_NAME = '$col' 
        AND REFERENCED_TABLE_NAME = '$refTable'
    ");
    
    if ($check && $check->num_rows > 0) {
        $existing = $check->fetch_assoc()['CONSTRAINT_NAME'];
        echo "Found existing constraint: $existing. Skipping.\n";
        return;
    }

    // 2. Clear orphan data first to prevent failure
    $conn->query("DELETE FROM `$table` WHERE `$col` NOT IN (SELECT id FROM `$refTable`)");
    
    // 3. Add constraint
    $sql = "ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` 
            FOREIGN KEY (`$col`) REFERENCES `$refTable`(`$refCol`) ON DELETE CASCADE";
    
    if ($conn->query($sql)) {
        echo "SUCCESS.\n";
    } else {
        echo "FAILED: " . $conn->error . "\n";
    }
}

// Apply to tables
safeAddConstraint($conn, 'siswa', 'user_id', 'users', 'id', 'fk_siswa_user_new');
safeAddConstraint($conn, 'guru_mapel', 'guru_id', 'users', 'id', 'fk_gurumapel_guru_new');
safeAddConstraint($conn, 'log_login', 'user_id', 'users', 'id', 'fk_loglogin_user');
safeAddConstraint($conn, 'notifikasi', 'user_id', 'users', 'id', 'fk_notifikasi_user');
safeAddConstraint($conn, 'dashboard_widgets', 'user_id', 'users', 'id', 'fk_widgets_user');

echo "Migration finished.\n";
?>