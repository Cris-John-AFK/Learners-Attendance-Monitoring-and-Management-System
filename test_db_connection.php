<?php
// Test database connection with different configurations
$configs = [
    [
        'type' => 'MySQL',
        'host' => 'localhost',
        'dbname' => 'lamms_db',
        'username' => 'root',
        'password' => '',
        'port' => '3306'
    ],
    [
        'type' => 'PostgreSQL', 
        'host' => 'localhost',
        'dbname' => 'lamms_db',
        'username' => 'postgres',
        'password' => 'password',
        'port' => '5432'
    ]
];

foreach ($configs as $config) {
    echo "Testing {$config['type']} connection...\n";
    
    try {
        if ($config['type'] === 'MySQL') {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        } else {
            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
        }
        
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✓ {$config['type']} connection successful!\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "✓ Query test successful: {$result['test']}\n";
        
        // Check if tables exist
        if ($config['type'] === 'MySQL') {
            $stmt = $pdo->query("SHOW TABLES");
        } else {
            $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        }
        
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "✓ Found " . count($tables) . " tables\n";
        
        if (count($tables) > 0) {
            echo "Tables: " . implode(', ', array_slice($tables, 0, 5)) . (count($tables) > 5 ? '...' : '') . "\n";
        }
        
        break; // Use the first working connection
        
    } catch (PDOException $e) {
        echo "✗ {$config['type']} connection failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}
?>
