<?php

/**
 * Database Connection Test Script
 *
 * This script tests the connection to the PostgreSQL database and reports any issues.
 * It also provides diagnostics info to help resolve connection problems.
 */

// Get database settings from .env file
$envFile = __DIR__ . '/.env';
$connectionSettings = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || empty($line)) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $connectionSettings[trim($name)] = trim($value);
    }
}

echo "=== PostgreSQL Database Connection Test ===\n\n";

// Verify pgsql extension is available
if (!extension_loaded('pgsql')) {
    echo "ERROR: PostgreSQL extension (pgsql) is not installed or not enabled.\n";
    echo "Please check your PHP installation and make sure the pgsql extension is enabled.\n";
    echo "You may need to uncomment or add 'extension=pgsql' in your php.ini file.\n\n";
    exit(1);
}

echo "PostgreSQL extension is available.\n";

// Build connection string
$host = $connectionSettings['DB_HOST'] ?? '127.0.0.1';
$port = $connectionSettings['DB_PORT'] ?? '5432';
$dbname = $connectionSettings['DB_DATABASE'] ?? 'lamms_db';
$user = $connectionSettings['DB_USERNAME'] ?? 'postgres';
$password = $connectionSettings['DB_PASSWORD'] ?? 'postgres';
$schema = $connectionSettings['DB_SCHEMA'] ?? 'public';
$sslmode = $connectionSettings['DB_PGSQL_SSLMODE'] ?? 'prefer';
$persistent = isset($connectionSettings['DB_PERSISTENT']) && $connectionSettings['DB_PERSISTENT'] === 'true';

// Setup DSN
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
if (!empty($schema)) {
    $dsn .= ";options='--search_path=$schema'";
}
if (!empty($sslmode)) {
    $dsn .= ";sslmode=$sslmode";
}

echo "Attempting to connect to: $host:$port/$dbname as user '$user'\n";
if ($persistent) {
    echo "Using persistent connections\n";
}

// Try the connection with a timeout
try {
    // Set connection options
    $options = [
        PDO::ATTR_TIMEOUT => 10, // 10 second timeout
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    if ($persistent) {
        $options[PDO::ATTR_PERSISTENT] = true;
    }

    $startTime = microtime(true);
    $pdo = new PDO($dsn, $user, $password, $options);
    $endTime = microtime(true);

    $connectionTime = round(($endTime - $startTime) * 1000);

    echo "SUCCESS: Connected to PostgreSQL database in {$connectionTime}ms.\n";

    // Test a simple query
    echo "Testing query execution... ";
    $startTime = microtime(true);
    $stmt = $pdo->query("SELECT 1");
    $endTime = microtime(true);
    $queryTime = round(($endTime - $startTime) * 1000);

    echo "Success in {$queryTime}ms.\n\n";

    // Database version info
    $versionStmt = $pdo->query("SELECT version()");
    $version = $versionStmt->fetchColumn();
    echo "Database Version: $version\n";

    // Check if required tables exist
    echo "\nChecking required tables:\n";
    $requiredTables = ['curricula', 'grades', 'sections', 'teachers', 'subjects'];
    $tablesQuery = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = '$schema'";
    $stmt = $pdo->query($tablesQuery);
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "- $table: Found\n";

            // Count rows
            $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $countStmt->fetchColumn();
            echo "  Row count: $count\n";
        } else {
            echo "- $table: Not found! This table is required for the application.\n";
        }
    }

    echo "\nPerformance Check:\n";

    // Check connection pool settings
    echo "- Connection Pooling: ";
    if (isset($connectionSettings['DB_CONNECTION_POOL'])) {
        echo "Configured with {$connectionSettings['DB_CONNECTION_POOL']} connections\n";
    } else {
        echo "Not configured (consider adding DB_CONNECTION_POOL to .env)\n";
    }

    // Check timeouts
    echo "- Statement Timeout: ";
    if (isset($connectionSettings['DB_STATEMENT_TIMEOUT'])) {
        echo "{$connectionSettings['DB_STATEMENT_TIMEOUT']}ms\n";
    } else {
        echo "Not configured (consider adding DB_STATEMENT_TIMEOUT to .env)\n";
    }

    // Check connection timeout
    echo "- Connection Timeout: ";
    if (isset($connectionSettings['DB_CONNECTION_TIMEOUT'])) {
        echo "{$connectionSettings['DB_CONNECTION_TIMEOUT']}s\n";
    } else {
        echo "Not configured (consider adding DB_CONNECTION_TIMEOUT to .env)\n";
    }

    // Check retry settings
    echo "- Max connection attempts: ";
    if (isset($connectionSettings['DB_MAX_ATTEMPTS'])) {
        echo "{$connectionSettings['DB_MAX_ATTEMPTS']}\n";
    } else {
        echo "Not configured (consider adding DB_MAX_ATTEMPTS to .env)\n";
    }

    echo "- Retry delay: ";
    if (isset($connectionSettings['DB_RETRY_DELAY'])) {
        echo "{$connectionSettings['DB_RETRY_DELAY']}s\n";
    } else {
        echo "Not configured (consider adding DB_RETRY_DELAY to .env)\n";
    }

    // Check for SSL mode
    echo "- SSL Mode: ";
    if (isset($connectionSettings['DB_PGSQL_SSLMODE'])) {
        echo "{$connectionSettings['DB_PGSQL_SSLMODE']}\n";
    } else {
        echo "Not configured (consider adding DB_PGSQL_SSLMODE=prefer to .env)\n";
    }

    // Check persistent connections
    echo "- Persistent connections: ";
    if (isset($connectionSettings['DB_PERSISTENT'])) {
        echo "{$connectionSettings['DB_PERSISTENT']}\n";
    } else {
        echo "Not configured (consider adding DB_PERSISTENT=true to .env)\n";
    }

    echo "\n=== Database connection test completed successfully ===\n";
} catch (PDOException $e) {
    echo "ERROR: Failed to connect to the PostgreSQL database.\n";
    echo "Error message: " . $e->getMessage() . "\n\n";

    // Provide more specific guidance based on the error
    if (strpos($e->getMessage(), 'could not connect to server') !== false) {
        echo "Possible causes:\n";
        echo "- PostgreSQL server is not running. Start the PostgreSQL service.\n";
        echo "- Incorrect host or port. Double-check DB_HOST and DB_PORT in .env file.\n";
        echo "- Firewall blocking the connection. Check firewall settings.\n";
    } elseif (strpos($e->getMessage(), 'password authentication failed') !== false) {
        echo "Possible causes:\n";
        echo "- Incorrect username or password. Double-check DB_USERNAME and DB_PASSWORD in .env file.\n";
        echo "- User does not have permission to access the database.\n";
    } elseif (strpos($e->getMessage(), 'database') !== false && strpos($e->getMessage(), 'does not exist') !== false) {
        echo "Possible causes:\n";
        echo "- Database does not exist. Create the database using the following commands:\n";
        echo "  1. Connect to PostgreSQL as a user with privileges: psql -U postgres\n";
        echo "  2. Create the database: CREATE DATABASE lamms_db;\n";
        echo "  3. Run migrations: php artisan migrate\n";
    } else {
        echo "Troubleshooting steps:\n";
        echo "1. Verify PostgreSQL server is running\n";
        echo "2. Check your .env file settings\n";
        echo "3. Ensure the database exists and is accessible\n";
        echo "4. Check for network issues (firewall, etc.)\n";
    }

    exit(1);
}
