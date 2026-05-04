<?php
declare(strict_types=1);

use App\Application\Middleware\SessionMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(SessionMiddleware::class);
    $app->add(function ($request, $handler) {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*') 
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    });
    // This ensures that all responses are treated as JSON by default
    $app->add(function ($request, $handler) {
        $response = $handler->handle($request);
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Add Error Middleware
    // The parameters are: displayErrorDetails, logErrors, logErrorDetails
    $app->addErrorMiddleware(true, true, true);
};