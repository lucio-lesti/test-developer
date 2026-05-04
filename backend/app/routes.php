<?php

declare(strict_types=1);

use App\Application\Actions\Person\PersonController;
use App\Application\Actions\ProblemSolving\DuplicateEmailsAction;
use App\Application\Actions\ProblemSolving\GroupEventsAction;
use App\Application\Actions\ProblemSolving\TreeAction;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Application\Middleware\ApiTokenMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.+}', function (Request $request, Response $response) {
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(['service' => 'test_backend', 'status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    $app->group('/persons', function (Group $group) {
        $group->post('/import', [PersonController::class, 'import']);
        $group->get('',         [PersonController::class, 'index']);
        $group->post('',        [PersonController::class, 'store']);
        $group->get('/{id}',    [PersonController::class, 'show']);
        $group->put('/{id}',    [PersonController::class, 'update']);
        $group->delete('/{id}', [PersonController::class, 'destroy']);
    })->add(ApiTokenMiddleware::class);

    $app->group('/problem-solving', function (Group $group) {
        $group->post('/duplicates', DuplicateEmailsAction::class);
        $group->post('/events', GroupEventsAction::class);
        $group->post('/tree', TreeAction::class);
    })->add(ApiTokenMiddleware::class);
    
};
