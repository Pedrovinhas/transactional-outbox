<?php

declare(strict_types=1);

namespace OutboxRelay;

use OutboxRelay\Database\OutboxRepository;
use OutboxRelay\Messaging\MessagePublisherInterface;
use OutboxRelay\Tracing\TracerFactory;
use OutboxRelay\Tracing\TraceContextHelper;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class OutboxRelay
{
    private OutboxRepository $repository;
    private MessagePublisherInterface $publisher;
    private Logger $logger;
    
    public function __construct(
        OutboxRepository $repository,
        MessagePublisherInterface $publisher
    ) {
        $this->repository = $repository;
        $this->publisher = $publisher;
        
        $this->logger = new Logger('outbox-relay');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }
    
    public function run(int $pollIntervalSeconds = 10): void
    {
        $this->logger->info("Outbox Relay Service started. Polling every {$pollIntervalSeconds}s");
        
        while (true) {
            try {
                $this->processOutbox();
                sleep($pollIntervalSeconds);
            } catch (\Exception $e) {
                $this->logger->error('Error processing outbox: ' . $e->getMessage());
                sleep($pollIntervalSeconds);
            }
        }
    }
    
    private function processOutbox(): void
    {
        $tracer = TracerFactory::create('outbox-relay');
        $pollSpan = $tracer->spanBuilder('outbox.poll_and_publish')
            ->startSpan();
        
        try {
            $events = $this->repository->getPendingEvents();
            
            if (empty($events)) {
                $pollSpan->setAttribute('events.count', 0);
                return;
            }
            
            $this->logger->info("Found " . count($events) . " events to publish");
            $pollSpan->setAttribute('events.count', count($events));
            
            foreach ($events as $event) {
                $this->publishEvent($tracer, $event);
            }
            
        } catch (\Exception $e) {
            $pollSpan->recordException($e);
            throw $e;
        } finally {
            $pollSpan->end();
        }
    }
    
    private function publishEvent($tracer, array $event): void
    {
        $traceContext = $event['trace_context'] ?? '';
        $parentContext = TraceContextHelper::extractParentContext($traceContext);
        
        $spanBuilder = $tracer->spanBuilder('outbox.publish_event')
            ->setAttribute('event.id', $event['id'])
            ->setAttribute('event.type', $event['event_type']);
        
        if ($parentContext !== null) {
            $spanBuilder->setParent($parentContext);
        }
        
        $eventSpan = $spanBuilder->startSpan();
        
        $propagatedContext = TraceContextHelper::serializeFromSpan($eventSpan);
        
        try {
            $this->publisher->publish($event, $propagatedContext);
            
            $this->repository->markAsPublished($event['id']);
            
            $this->logger->info("Published event {$event['id']} - {$event['event_type']}");
            
        } catch (\Exception $e) {
            $eventSpan->recordException($e);
            $this->logger->error("Failed to publish event {$event['id']}: " . $e->getMessage());
        } finally {
            $eventSpan->end();
        }
    }
    
    public function __destruct()
    {
        $this->publisher->close();
    }
}

