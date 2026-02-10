<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use OutboxRelay\OutboxRelay;
use OutboxRelay\Database\OutboxRepository;
use OutboxRelay\Messaging\RabbitMQPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PDO;

$dbHost = getenv('DB_HOST') ?: 'postgres';
$dbName = getenv('DB_NAME') ?: 'orders_db';
$dbUser = getenv('DB_USER') ?: 'postgres';
$dbPass = getenv('DB_PASSWORD') ?: 'postgres';

$rabbitHost = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
$rabbitPort = (int) (getenv('RABBITMQ_PORT') ?: 5672);
$rabbitUser = getenv('RABBITMQ_USER') ?: 'guest';
$rabbitPass = getenv('RABBITMQ_PASSWORD') ?: 'guest';

$pollInterval = (int) (getenv('POLL_INTERVAL') ?: 10);

try {
    $dsn = "pgsql:host={$dbHost};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $rabbitConnection = new AMQPStreamConnection(
        $rabbitHost,
        $rabbitPort,
        $rabbitUser,
        $rabbitPass
    );
    
    $repository = new OutboxRepository($pdo);
    $publisher = new RabbitMQPublisher($rabbitConnection);
    
    $relay = new OutboxRelay($repository, $publisher);
    $relay->run($pollInterval);
    
} catch (\Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
