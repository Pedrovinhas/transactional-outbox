<?php

declare(strict_types=1);

namespace PostProcessing\Messaging;

interface MessageConsumerInterface
{
    /**
     * Start consuming messages from the message broker
     * 
     * @param callable $callback Callback function to process each message
     * @return void
     * @throws \Exception If consuming fails
     */
    public function consume(callable $callback): void;
    
    /**
     * Close the connection to the message broker
     * 
     * @return void
     */
    public function close(): void;
}
