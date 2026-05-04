<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
(require __DIR__ . '/../app/settings.php')($containerBuilder);
(require __DIR__ . '/../app/dependencies.php')($containerBuilder);
$container = $containerBuilder->build();

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);
$db = $settings->get('db');
$driver = $db['driver'];

$file = __DIR__ . '/../migrations/persons.' . $driver . '.sql';
if (!is_file($file)) {
    fwrite(STDERR, "No migration file for driver '$driver' at $file\n");
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Failed to read migration file.\n");
    exit(1);
}

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $pdo->exec($statement);
}

echo "Migration applied for driver '$driver'.\n";
