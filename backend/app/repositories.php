<?php

declare(strict_types=1);

use App\Domain\Person\PersonRepository;
use App\Infrastructure\Persistence\Person\PdoPersonRepository;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        PersonRepository::class => function (ContainerInterface $c) {
            // Get PDO from the container (configured in dependencies.php)
            $db = $c->get(PDO::class); 
            return new PdoPersonRepository($db);
        },
    ]);
};
