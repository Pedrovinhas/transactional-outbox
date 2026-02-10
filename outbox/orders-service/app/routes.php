<?php

declare(strict_types=1);

use App\Application\Actions\Order\CreateOrderAction;
use App\Application\Actions\Order\ListOrdersAction;
use App\Application\Actions\Order\ViewOrderAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'service' => 'Orders Service',
            'status' => 'running'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/orders', function (Group $group) {
        $group->get('', ListOrdersAction::class);
        $group->post('', CreateOrderAction::class);
        $group->get('/{id}', ViewOrderAction::class);
    });
};
