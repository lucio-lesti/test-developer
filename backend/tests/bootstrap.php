<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=test');
putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=:memory:');
