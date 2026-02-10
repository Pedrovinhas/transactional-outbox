<?php

declare(strict_types=1);

namespace PostProcessing\Database;

use PDO;

class InboxRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function saveEvent(array $event, string $traceContext = ''): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO inbox (event_id, aggregate_id, event_type, payload, trace_context, received_at) 
             VALUES (:event_id, :aggregate_id, :event_type, :payload, :trace_context, NOW())
             ON CONFLICT (event_id) DO NOTHING"
        );
        
        $stmt->execute([
            'event_id' => $event['event_id'],
            'aggregate_id' => $event['aggregate_id'],
            'event_type' => $event['event_type'],
            'payload' => json_encode($event['payload']),
            'trace_context' => $traceContext ?: null
        ]);
    }
}
