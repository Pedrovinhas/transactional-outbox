<?php

declare(strict_types=1);

namespace PostProcessing;

use PostProcessing\Database\InboxRepository;
use PostProcessing\Messaging\MessageConsumerInterface;
use PostProcessing\Tracing\TracerFactory;
use PostProcessing\Tracing\TraceContextHelper;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class InboxConsumer
{
    private InboxRepository $repository;
    private MessageConsumerInterface $consumer;
    private Logger $logger;
    
    public function __construct(
        InboxRepository $repository,
        MessageConsumerInterface $consumer
    ) {
        $this->repository = $repository;
        $this->consumer = $consumer;
        
        $this->logger = new Logger('inbox-consumer');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }
    
    public function consume(): void
    {
        $this->logger->info("Inbox Consumer started. Waiting for messages...");
        
        $callback = function (array $data) {
            $this->processMessage($data);
        };
        
        $this->consumer->consume($callback);
    }
    
    private function processMessage(array $data): void
    {
        $traceContext = $data['_trace_context'] ?? '';
        unset($data['_trace_context']);
        
        $tracer = TracerFactory::create('post-processing');
        $parentContext = TraceContextHelper::extractParentContext($traceContext);
        
        $spanBuilder = $tracer->spanBuilder('inbox.process_event')
            ->setAttribute('event.id', $data['event_id'])
            ->setAttribute('event.type', $data['event_type']);
        
        if ($parentContext !== null) {
            $spanBuilder->setParent($parentContext);
        }
        
        $span = $spanBuilder->startSpan();
        
        $updatedTraceContext = TraceContextHelper::serializeFromSpan($span);
        
        try {
            $this->logger->info("Processing event: {$data['event_type']} - Event ID: {$data['event_id']}");
            
            $this->repository->saveEvent($data, $updatedTraceContext);
            
            $this->logger->info("Event {$data['event_id']} saved to inbox table");
            
        } catch (\Exception $e) {
            $span->recordException($e);
            $this->logger->error("Failed to process event: " . $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }
    
    public function __destruct()
    {
        $this->consumer->close();
    }
}
