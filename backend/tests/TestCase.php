<?php

declare(strict_types=1);

namespace Tests;

use DI\ContainerBuilder;
use Exception;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Uri;

class TestCase extends PHPUnit_TestCase
{
    use ProphecyTrait;

    /**
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        $containerBuilder = new ContainerBuilder();

        (require __DIR__ . '/../app/settings.php')($containerBuilder);
        (require __DIR__ . '/../app/dependencies.php')($containerBuilder);
        (require __DIR__ . '/../app/repositories.php')($containerBuilder);

        $container = $containerBuilder->build();

        $this->migrateSqlite($container->get(PDO::class));

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        (require __DIR__ . '/../app/routes.php')($app);
        (require __DIR__ . '/../app/middleware.php')($app);

        return $app;
    }

    private function migrateSqlite(PDO $pdo): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../migrations/persons.sqlite.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Calcola il token API atteso leggendo lo stesso settings.php usato dall'app.
     * Cached per esecuzione del processo di test.
     */
    protected function apiToken(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cb = new ContainerBuilder();
        (require __DIR__ . '/../app/settings.php')($cb);
        $settings = $cb->build()->get(\App\Application\Settings\SettingsInterface::class);
        $tokenSettings = (array) $settings->get('token');
        $cached = bin2hex(sodium_crypto_generichash((string) $tokenSettings['secret']));
        return $cached;
    }

    /**
     * @param array<string,string> $headers
     * @param array<string,string> $cookies
     * @param array<string,mixed>  $serverParams
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = [],
        bool $authenticate = true
    ): Request {
        $query = '';
        if (($qPos = strpos($path, '?')) !== false) {
            $query = substr($path, $qPos + 1);
            $path = substr($path, 0, $qPos);
        }

        $uri = new Uri('', '', 80, $path, $query);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        if ($authenticate && !isset($headers['X-API-Token'])) {
            $headers['X-API-Token'] = $this->apiToken();
        }

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    /**
     * @param array<string,mixed> $body
     */
    protected function jsonRequest(string $method, string $path, array $body = [], bool $authenticate = true): Request
    {
        $headers = [
            'HTTP_ACCEPT'  => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $request = $this->createRequest($method, $path, $headers, [], [], $authenticate);
        $request->getBody()->write(json_encode($body));
        $request->getBody()->rewind();
        return $request;
    }

    /**
     * @return array<string,mixed>
     */
    protected function jsonBody(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
