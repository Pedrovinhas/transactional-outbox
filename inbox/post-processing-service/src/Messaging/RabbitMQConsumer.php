<?php

declare(strict_types=1);

namespace PostProcessing\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQConsumer implements MessageConsumerInterface
{
    private AMQPStreamConnection $connection;
    private string $exchange;
    private string $queueName;
    
    public function __construct(
        AMQPStreamConnection $connection,
        string $exchange = 'orders_exchange',
        string $queueName = 'inbox_queue'
    ) {
        $this->connection = $connection;
        $this->exchange = $exchange;
        $this->queueName = $queueName;
    }
    
    public function consume(callable $callback): void
    {
        $channel = $this->connection->channel();
        
        $channel->exchange_declare($this->exchange, 'topic', false, true, false);
        
        $channel->queue_declare($this->queueName, false, true, false, false);
        
        $channel->queue_bind($this->queueName, $this->exchange, 'order.*');
        
        $messageHandler = function (AMQPMessage $msg) use ($callback) {
            try {
                $data = json_decode($msg->body, true);
                
                if (!$data) {
                    throw new \RuntimeException('Invalid JSON in message');
                }
                
                $traceContext = '';
                if ($msg->has('application_headers')) {
                    $headers = $msg->get('application_headers');
                    if ($headers instanceof AMQPTable) {
                        $nativeHeaders = $headers->getNativeData();
                        $traceContext = $nativeHeaders['traceparent'] ?? '';
                    }
                }
                
                $data['_trace_context'] = $traceContext;
                
                $callback($data);
                $msg->ack();
                
            } catch (\Exception $e) {
                $msg->nack(true);
                throw $e;
            }
        };
        
        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($this->queueName, '', false, false, false, false, $messageHandler);
        
        while ($channel->is_consuming()) {
            $channel->wait();
        }
        
        $channel->close();
    }
    
    public function close(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }
}
