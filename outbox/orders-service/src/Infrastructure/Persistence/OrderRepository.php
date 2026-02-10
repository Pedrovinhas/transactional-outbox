<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use App\Domain\Order\Order;

class OrderRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function createOrderWithOutboxEvent(Order $order, string $traceContext = ''): Order
    {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO orders (customer_name, customer_email, total_amount, status, created_at) 
                 VALUES (:customer_name, :customer_email, :total_amount, :status, NOW()) 
                 RETURNING id"
            );
            
            $stmt->execute([
                'customer_name' => $order->getCustomerName(),
                'customer_email' => $order->getCustomerEmail(),
                'total_amount' => $order->getTotalAmount(),
                'status' => $order->getStatus()
            ]);
            
            $orderId = (int) $stmt->fetchColumn();
            $order->setId($orderId);
            
            $eventPayload = json_encode([
                'order_id' => $orderId,
                'customer_name' => $order->getCustomerName(),
                'customer_email' => $order->getCustomerEmail(),
                'total_amount' => $order->getTotalAmount(),
                'status' => $order->getStatus()
            ]);
            
            $outboxStmt = $this->pdo->prepare(
                "INSERT INTO outbox (aggregate_id, event_type, payload, trace_context, created_at) 
                 VALUES (:aggregate_id, :event_type, :payload, :trace_context, NOW())"
            );
            
            $outboxStmt->execute([
                'aggregate_id' => $orderId,
                'event_type' => 'order.created',
                'payload' => $eventPayload,
                'trace_context' => $traceContext ?: null
            ]);
            
            $this->pdo->commit();
            
            return $order;
            
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function findById(int $id): ?Order
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, customer_name, customer_email, total_amount, status, created_at 
             FROM orders 
             WHERE id = :id"
        );
        
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new Order(
            $data['customer_name'],
            $data['customer_email'],
            (float) $data['total_amount'],
            $data['status'],
            new \DateTimeImmutable($data['created_at']),
            (int) $data['id']
        );
    }
    
    public function findAll(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, customer_name, customer_email, total_amount, status, created_at 
             FROM orders 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $orders = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = new Order(
                $data['customer_name'],
                $data['customer_email'],
                (float) $data['total_amount'],
                $data['status'],
                new \DateTimeImmutable($data['created_at']),
                (int) $data['id']
            );
        }
        
        return $orders;
    }
}
