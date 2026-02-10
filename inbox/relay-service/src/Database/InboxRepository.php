<?php

declare(strict_types=1);

namespace InboxRelay\Database;

use PDO;

class InboxRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function getUnprocessedMessages(int $limit = 100): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, event_id, aggregate_id, event_type, payload, trace_context, received_at 
             FROM inbox 
             WHERE processed = false 
             ORDER BY received_at ASC 
             LIMIT {$limit}"
        );
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function markAsProcessed(int $messageId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE inbox SET processed = true, processed_at = NOW() WHERE id = :id"
        );
        
        $stmt->execute(['id' => $messageId]);
    }
}
