<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use Tests\TestCase;

/**
 * Test di integrazione del middleware ApiTokenMiddleware applicato ai gruppi
 * /persons e /problem-solving in app/routes.php.
 *
 * Verifica che le richieste senza token (o con token errato) ricevano 401,
 * e che le richieste con token valido attraversino normalmente.
 */
final class ApiTokenMiddlewareTest extends TestCase
{
    /** Richiesta senza header X-API-Token: 401 e tipo errore UNAUTHENTICATED. */
    public function testRequestWithoutTokenIsRejected(): void
    {
        $app = $this->getAppInstance();
        // Quinto argomento $authenticate=false: NON inietta il token di default.
        $response = $app->handle($this->createRequest('GET', '/persons', ['HTTP_ACCEPT' => 'application/json'], [], [], false));
        $body = $this->jsonBody($response);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $body['error']['type']);
    }

    /** Richiesta con header X-API-Token errato: 401. */
    public function testRequestWithWrongTokenIsRejected(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest(
            'GET',
            '/persons',
            ['HTTP_ACCEPT' => 'application/json', 'X-API-Token' => 'token-fasullo'],
            [], [], false
        ));

        $this->assertSame(401, $response->getStatusCode());
    }

    /** Richiesta con token valido: 200, l'azione viene eseguita normalmente. */
    public function testRequestWithValidTokenIsAccepted(): void
    {
        $app = $this->getAppInstance();
        // createRequest(..., authenticate: true) inietta il token di default.
        $response = $app->handle($this->createRequest('GET', '/persons'));
        $this->assertSame(200, $response->getStatusCode());
    }

    /** Anche /problem-solving è protetto dal middleware. */
    public function testProblemSolvingGroupIsAlsoProtected(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest(
            'POST',
            '/problem-solving/duplicates',
            ['HTTP_ACCEPT' => 'application/json'],
            [], [], false
        ));

        $this->assertSame(401, $response->getStatusCode());
    }
}
