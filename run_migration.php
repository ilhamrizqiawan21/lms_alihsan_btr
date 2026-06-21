<?php
/**
 * Migration Runner
 * Run database migrations to set up dashboard_widgets table
 * 
 * Usage: 
 * - Via terminal: php run_migration.php
 * - Via browser: http://localhost/lms_alihsan_btr/run_migration.php?key=YOUR_SECRET_KEY
 */

// For security, require a key or CLI context
$is_cli = php_sapi_name() === 'cli';
$has_key = isset($_GET['key']) && $_GET['key'] === getenv('MIGRATION_KEY');

if (!$is_cli && !$has_key) {
    http_response_code(403);
    die('❌ Unauthorized. Migration key required.');
}

include 'config.php';

$migrations = [
    'migrations/001_add_siswa_status.sql',
    'migrations/002_dashboard_widgets.sql',
    'migrations/003_calendar_events.sql',
    'migrations/004_alter_calendar_events.sql',
    'migrations/005_archive_system_updates.sql',
    'migrations/006_fix_user_integrity.sql',
    'migrations/007_multiple_file_upload.sql'
];

$output = [];
$all_success = true;

foreach ($migrations as $migration_file) {
    if (!file_exists($migration_file)) {
        $output[] = "⚠️  Skipped (not found): $migration_file";
        continue;
    }
    
    $sql = file_get_contents($migration_file);
    
    // Split SQL statements (handle comments)
    $statements = [];
    $current_stmt = '';
    $in_comment = false;
    
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        
        // Skip comment lines
        if (strpos($line, '--') === 0 || strpos($line, '#') === 0) continue;
        if (strpos($line, '/*') !== false) $in_comment = true;
        if (strpos($line, '*/') !== false) { $in_comment = false; continue; }
        if ($in_comment) continue;
        
        $current_stmt .= ' ' . $line;
        
        if (substr($line, -1) === ';') {
            $statements[] = rtrim($current_stmt, ';');
            $current_stmt = '';
        }
    }
    
    // Execute statements
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        if (!$conn->query($stmt)) {
            $output[] = "❌ Failed: $migration_file";
            $output[] = "   Error: " . $conn->error;
            $all_success = false;
            break;
        }
    }
    
    if ($all_success) {
        $output[] = "✅ Completed: $migration_file";
    }
}

$output[] = '';
if ($all_success) {
    $output[] = "✅ All migrations completed successfully!";
} else {
    $output[] = "❌ Some migrations failed. See details above.";
}

if ($is_cli) {
    echo implode("\n", $output) . "\n";
    exit($all_success ? 0 : 1);
} else {
    header('Content-Type: text/plain; charset=utf-8');
    die(implode("\n", $output));
}
