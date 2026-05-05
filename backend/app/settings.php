<?php

declare(strict_types=1);

use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {

    // Oggetto delle impostazioni globali
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            // Connessione al database: in test (APP_ENV=test, DB_DRIVER=sqlite)
            // si usa SQLite (in-memory di default) per isolare i test;
            // altrimenti si usano le credenziali MySQL hard-coded di sviluppo.
            $driver = getenv('DB_DRIVER') ?: 'mysql';
            if ($driver === 'sqlite') {
                $dbPath = getenv('DB_PATH') ?: ':memory:';
                $db = [
                    'driver'   => 'sqlite',
                    'dsn'      => 'sqlite:' . $dbPath,
                    'username' => null,
                    'password' => null,
                    'options'  => [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ],
                ];
            } else {
                $db = [
                    'driver'   => 'mysql',
                    'dsn'      => 'mysql:host=dbhost;dbname=test;charset=utf8mb4',
                    'username' => 'lucius',
                    'password' => 'Lucius76@!',
                    'options'  => [
                        PDO::ATTR_PERSISTENT         => true,
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ],
                ];
            }

            return new Settings([
                'displayErrorDetails' => true, // In produzione impostare a false
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Logger::DEBUG,
                ],
                'db' => $db,
                'uploads' => [
                    'dir'            => __DIR__ . '/../var/uploads',
                    'maxBytes'       => 5 * 1024 * 1024,
                    'allowedMimes'   => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                    'allowedHeaders' => [
                        'first_name', 'last_name', 'email',
                        'date_of_birth', 'phone_number', 'role', 'notes',
                    ],
                ],

                'token' => [
                    'secret' => 'MQ1WVRQ7FObrcoc1496bZva1ms0kEwfD5YhDavz2YqfgcoBmfct1bvpLrF3Ez5cu',
                    'header' => 'X-API-Token',
                ],
            ]);
        }
    ]);
};
