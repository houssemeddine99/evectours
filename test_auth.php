<?php

require_once 'vendor/autoload.php';

// Load .env file
if (file_exists(__DIR__.'/.env')) {
    $lines = file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

echo "=== Database Health Check ===\n";

// Test database connection using PDO directly
try {
    $dsn = $_ENV['DATABASE_URL'] ?? '';
    
    // Parse PostgreSQL URL
    if (preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/([^?]+)\?(.*)/', $dsn, $matches)) {
        $user = $matches[1];
        $pass = $matches[2];
        $host = $matches[3];
        $port = $matches[4];
        $dbname = $matches[5];
        
        echo "Connecting to: {$host}:{$port}/{$dbname}\n";
        
        $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✓ Database connection: OK\n";
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        echo "✓ Users table accessible: OK ({$userCount} users found)\n";
        
        // Check if specific user exists
        $stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE email = ?");
        $stmt->execute(['faadmin@travagir.com']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\n=== Testing Login ===\n";
        echo "Testing login for: faadmin@travagir.com\n";
        
        if ($user) {
            echo "Email exists: YES\n";
            echo "  User ID: {$user['id']}\n";
            echo "  Username: {$user['username']}\n";
            echo "  Email: {$user['email']}\n";
            echo "  Password hash: " . substr($user['password'], 0, 20) . "...\n";
            
            // Test password verification
            $password = "123Fares.";
            if (password_verify($password, $user['password'])) {
                echo "✓ Login: SUCCESS\n";
            } else {
                echo "✗ Login: FAILED\n";
                echo "  Reason: Incorrect password\n";
            }
        } else {
            echo "Email exists: NO\n";
            echo "✗ Login: FAILED\n";
            echo "  Reason: Email not found\n";
        }
    } else {
        echo "✗ Database connection: FAILED\n";
        echo "  Error: Could not parse DATABASE_URL\n";
        echo "  URL: {$dsn}\n";
    }
} catch (\Throwable $e) {
    echo "✗ Database connection: FAILED\n";
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
