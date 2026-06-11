#!/bin/bash
# Migration runner script
# Usage: bash run_migration.sh [migration_file]

cd "$(dirname "$0")" || exit 1

# Load database credentials from config.php
php -r "
include 'config.php';

function run_migration(\$filename) {
    global \$conn;
    
    if (!file_exists(\$filename)) {
        echo \"Error: Migration file not found: \$filename\n\";
        return false;
    }
    
    \$sql = file_get_contents(\$filename);
    
    // Split by semicolon but preserve them in statements
    \$statements = array_filter(array_map('trim', preg_split('/;(?=\s*(--|#|\/\*|$))/m', \$sql)));
    
    foreach (\$statements as \$statement) {
        if (empty(\$statement) || strpos(\$statement, '--') === 0) continue;
        
        if (!\$conn->query(\$statement)) {
            echo \"Error executing statement: \" . \$conn->error . \"\n\";
            echo \"Statement: \$statement\n\";
            return false;
        }
    }
    
    return true;
}

\$migration = \$GLOBALS['argv'][1] ?? 'migrations/002_dashboard_widgets.sql';

if (run_migration(\$migration)) {
    echo \"✅ Migration succeeded: \$migration\n\";
    exit(0);
} else {
    echo \"❌ Migration failed: \$migration\n\";
    exit(1);
}
" "$1"

