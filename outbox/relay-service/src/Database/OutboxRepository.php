<?php

declare(strict_types=1);

namespace OutboxRelay\Database;

use PDO;

class OutboxRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function getPendingEvents(int $limit = 100): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, aggregate_id, event_type, payload, trace_context, created_at 
             FROM outbox 
             WHERE published = false 
             ORDER BY created_at ASC 
             LIMIT {$limit}"
        );
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function markAsPublished(int $eventId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE outbox SET published = true, published_at = NOW() WHERE id = :id"
        );
        
        $stmt->execute(['id' => $eventId]);
    }
}
