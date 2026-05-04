<?php
declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;


use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\CallableResolverInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // 1. Add the Response Factory (Required by ErrorMiddleware)
        ResponseFactoryInterface::class => function (ContainerInterface $c) {
            return AppFactory::determineResponseFactory();
        },

        // 2. Add the Callable Resolver (Required by ErrorMiddleware)
        CallableResolverInterface::class => function (ContainerInterface $c) {
            // We need the 'App' instance to get its resolver, 
            // but usually, we just create a new one for the container:
            return new \Slim\CallableResolver($c);
        },

        // Requirement: Monolog 2 setup for logging
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        // Database Connection (PDO)
        // This is shared by both SQLite and MySQL implementations
        PDO::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);
            $dbSettings = $settings->get('db');

            $dsn = $dbSettings['dsn'];
            $username = $dbSettings['username'] ?? null;
            $password = $dbSettings['password'] ?? null;
            $options = $dbSettings['options'] ?? [];

            try {
                $pdo = new PDO($dsn, $username, $password, $options);
                
                // Requirement A1: Mitigate information leakage 
                // Set error mode to Exception so Infrastructure can catch them and hide details from user
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Standardize result sets as associative arrays
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                return $pdo;
            } catch (PDOException $e) {
                // Requirement A1: Log real error but don't expose to the browser/client
                $logger = $c->get(LoggerInterface::class);
                $logger->error("Database connection failed: " . $e->getMessage());
                
                throw new \RuntimeException("Internal Server Error occurred while connecting to data.");
            }
        },
    ]);
};