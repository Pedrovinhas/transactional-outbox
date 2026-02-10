<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use InboxRelay\InboxRelay;
use InboxRelay\Database\InboxRepository;
use PDO;

$dbHost = getenv('DB_HOST') ?: 'postgres';
$dbName = getenv('DB_NAME') ?: 'inbox_db';
$dbUser = getenv('DB_USER') ?: 'postgres';
$dbPass = getenv('DB_PASSWORD') ?: 'postgres';

$pollInterval = (int) (getenv('POLL_INTERVAL') ?: 10);

try {
    $dsn = "pgsql:host={$dbHost};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $repository = new InboxRepository($pdo);
    
    $relay = new InboxRelay($repository);
    $relay->run($pollInterval);
    
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
