<?php

declare(strict_types=1);

namespace App\Application\Actions\Order;

use App\Infrastructure\Persistence\OrderRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ListOrdersAction
{
    private OrderRepository $orderRepository;
    
    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }
    
    public function __invoke(Request $request, Response $response): Response
    {
        $orders = $this->orderRepository->findAll();
        
        $ordersData = array_map(function($order) {
            return $order->toArray();
        }, $orders);
        
        $response->getBody()->write(json_encode([
            'orders' => $ordersData,
            'count' => count($ordersData)
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
