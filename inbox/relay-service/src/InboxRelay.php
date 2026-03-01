<?php

declare(strict_types=1);

namespace InboxRelay;

use InboxRelay\Database\InboxRepository;
use InboxRelay\Tracing\TracerFactory;
use InboxRelay\Tracing\TraceContextHelper;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class InboxRelay
{
    private InboxRepository $repository;
    private Logger $logger;
    
    public function __construct(InboxRepository $repository)
    {
        $this->repository = $repository;
        
        $this->logger = new Logger('inbox-relay');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }
    
    public function run(int $pollIntervalSeconds = 10): void
    {
        $this->logger->info("Inbox Relay Service started. Polling every {$pollIntervalSeconds}s");
        
        while (true) {
            try {
                $this->processInbox();
                sleep($pollIntervalSeconds);
            } catch (\Exception $e) {
                $this->logger->error('Error processing inbox: ' . $e->getMessage());
                sleep($pollIntervalSeconds);
            }
        }
    }
    
    private function processInbox(): void
    {
        $tracer = TracerFactory::create('inbox-relay');
        $pollSpan = $tracer->spanBuilder('inbox.poll_and_process')
            ->startSpan();
        
        try {
            $messages = $this->repository->getUnprocessedMessages();
            
            if (empty($messages)) {
                $pollSpan->setAttribute('messages.count', 0);
                return;
            }
            
            $this->logger->info("Found " . count($messages) . " messages to process");
            $pollSpan->setAttribute('messages.count', count($messages));
            
            foreach ($messages as $message) {
                $this->processMessage($tracer, $message);
            }
            
        } catch (\Exception $e) {
            $pollSpan->recordException($e);
            throw $e;
        } finally {
            $pollSpan->end();
        }
    }
    
    private function processMessage($tracer, array $message): void
    {
        $traceContext = $message['trace_context'] ?? '';
        $parentContext = TraceContextHelper::extractParentContext($traceContext);
        
        $spanBuilder = $tracer->spanBuilder('inbox.process_message')
            ->setAttribute('message.id', $message['id'])
            ->setAttribute('event.type', $message['event_type']);
        
        if ($parentContext !== null) {
            $spanBuilder->setParent($parentContext);
        }
        
        $messageSpan = $spanBuilder->startSpan();
        
        try {
            $businessSpan = $tracer->spanBuilder('business_logic.execute')
                ->setAttribute('event.type', $message['event_type'])
                ->startSpan();
            
            $this->processBusinessLogic($message);
            $businessSpan->end();
            
            $this->repository->markAsProcessed($message['id']);
            
            $this->logger->info("Processed message {$message['id']} - {$message['event_type']}");
            
        } catch (\Exception $e) {
            $messageSpan->recordException($e);
            $this->logger->error("Failed to process message {$message['id']}: " . $e->getMessage());
        } finally {
            $messageSpan->end();
        }
    }
    
    private function processBusinessLogic(array $message): void
    {
        $eventType = $message['event_type'];
        $payload = json_decode($message['payload'], true);
        
        $this->logger->info("Executing business logic for: {$eventType}");
        
        switch ($eventType) {
            case 'order.created':
                $this->handleOrderCreated($payload);
                break;
                
            case 'order.updated':
                $this->handleOrderUpdated($payload);
                break;
                
            case 'order.cancelled':
                $this->handleOrderCancelled($payload);
                break;
                
            default:
                $this->logger->warning("Unknown event type: {$eventType}");
        }
    }
    
    private function handleOrderCreated(array $payload): void
    {
        // Example: Send email, call external API, etc.
        $this->logger->info("Order created - Customer: {$payload['customer_name']}, Total: {$payload['total_amount']}");
        
        // TODO: Implement actual business logic
        // - Send confirmation email
        // - Notify warehouse system
        // - Update analytics
    }
    
    private function handleOrderUpdated(array $payload): void
    {
        $this->logger->info("Order updated - Order ID: {$payload['order_id']}");
        
        // TODO: Implement actual business logic
    }
    
    private function handleOrderCancelled(array $payload): void
    {
        $this->logger->info("Order cancelled - Order ID: {$payload['order_id']}");
        
        // TODO: Implement actual business logic
        // - Send cancellation email
        // - Process refund
        // - Update inventory
    }
}
