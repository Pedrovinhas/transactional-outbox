<?php

declare(strict_types=1);

namespace App\Application\Actions\Order;

use App\Infrastructure\Persistence\OrderRepository;
use App\Infrastructure\Tracing\TracerFactory;
use App\Infrastructure\Tracing\TraceContextHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class CreateOrderAction
{
    private OrderRepository $orderRepository;
    private LoggerInterface $logger;
    
    public function __construct(
        OrderRepository $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }
    
    public function __invoke(Request $request, Response $response): Response
    {
        $tracer = TracerFactory::create('orders-service');
        $span = $tracer->spanBuilder('order.create')
            ->setAttribute('http.method', 'POST')
            ->setAttribute('http.route', '/orders')
            ->startSpan();
        
        try {
            $data = $request->getParsedBody();
            
            $errors = $this->validate($data);
            
            if (!empty($errors)) {
                $span->setAttribute('validation.failed', true);
                $span->setAttribute('validation.errors', json_encode($errors));
                
                $response->getBody()->write(json_encode([
                    'error' => 'Validation failed',
                    'details' => $errors
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }
            
            $order = new \App\Domain\Order\Order(
                $data['customer_name'],
                $data['customer_email'],
                (float) $data['total_amount']
            );
            
            $traceContext = TraceContextHelper::serializeFromSpan($span);
            
            $order = $this->orderRepository->createOrderWithOutboxEvent($order, $traceContext);
            
            $span->setAttribute('order.id', $order->getId());
            $span->setAttribute('customer.name', $order->getCustomerName());
            $span->setAttribute('order.total', $order->getTotalAmount());
            
            $this->logger->info("Order created successfully", [
                'order_id' => $order->getId(),
                'customer' => $order->getCustomerName()
            ]);
            
            $response->getBody()->write(json_encode([
                'message' => 'Order created successfully',
                'order' => $order->toArray()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
                
        } catch (\Exception $e) {
            $span->recordException($e);
            
            $this->logger->error('Failed to create order: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'error' => 'Failed to create order',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        } finally {
            $span->end();
        }
    }
    
    private function validate(?array $data): array
    {
        $errors = [];
        
        if (empty($data['customer_name'])) {
            $errors[] = 'customer_name is required';
        }
        
        if (empty($data['customer_email'])) {
            $errors[] = 'customer_email is required';
        } elseif (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'customer_email must be a valid email';
        }
        
        if (!isset($data['total_amount']) || $data['total_amount'] <= 0) {
            $errors[] = 'total_amount must be greater than 0';
        }
        
        return $errors;
    }
}
