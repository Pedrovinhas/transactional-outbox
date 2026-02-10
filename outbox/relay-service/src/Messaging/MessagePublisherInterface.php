<?php

declare(strict_types=1);

namespace OutboxRelay\Messaging;

interface MessagePublisherInterface
{
    /**
     * Publish an event to the message broker
     * 
     * @param array $event Event data to publish
     * @param string $traceContext W3C traceparent header for distributed tracing
     * @return void
     * @throws \Exception If publishing fails
     */
    public function publish(array $event, string $traceContext = ''): void;
    
    /**
     * Close the connection to the message broker
     * 
     * @return void
     */
    public function close(): void;
}
