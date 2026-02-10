<?php

declare(strict_types=1);

namespace OutboxRelay\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQPublisher implements MessagePublisherInterface
{
    private AMQPStreamConnection $connection;
    private string $exchange;
    
    public function __construct(
        AMQPStreamConnection $connection,
        string $exchange = 'orders_exchange'
    ) {
        $this->connection = $connection;
        $this->exchange = $exchange;
    }
    
    public function publish(array $event, string $traceContext = ''): void
    {
        $channel = $this->connection->channel();
        
        $channel->exchange_declare($this->exchange, 'topic', false, true, false);
        
        $messageBody = json_encode([
            'event_id' => $event['id'],
            'aggregate_id' => $event['aggregate_id'],
            'event_type' => $event['event_type'],
            'payload' => json_decode($event['payload'], true),
            'created_at' => $event['created_at']
        ]);
        
        $messageProperties = [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json'
        ];
        
        if (!empty($traceContext)) {
            $messageProperties['application_headers'] = new AMQPTable([
                'traceparent' => $traceContext
            ]);
        }
        
        $message = new AMQPMessage($messageBody, $messageProperties);
        
        $routingKey = $event['event_type'];
        $channel->basic_publish($message, $this->exchange, $routingKey);
        
        $channel->close();
    }
    
    public function close(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }
}
