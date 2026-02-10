<?php

declare(strict_types=1);

namespace App\Application\Actions\Order;

use App\Infrastructure\Persistence\OrderRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ViewOrderAction
{
    private OrderRepository $orderRepository;
    
    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }
    
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) $args['id'];
        $order = $this->orderRepository->findById($orderId);
        
        if (!$order) {
            $response->getBody()->write(json_encode([
                'error' => 'Order not found'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($order->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
