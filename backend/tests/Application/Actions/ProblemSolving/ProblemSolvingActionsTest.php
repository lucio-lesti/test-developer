<?php

declare(strict_types=1);

namespace Tests\Application\Actions\ProblemSolving;

use Tests\TestCase;

/**
 * Test di integrazione per gli endpoint /problem-solving/{duplicates,events,tree}.
 *
 * Verificano la pipeline HTTP completa: routing, validazione del body,
 * invocazione del servizio di dominio e forma del payload di risposta.
 */
final class ProblemSolvingActionsTest extends TestCase
{
    /** Endpoint duplicates: lista ordinata e normalizzata sotto data.duplicates. */
    public function testDuplicatesEndpoint(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/duplicates', [
            'emails' => [' Alice@example.com ', 'bob@example.com', 'alice@example.com', 'BOB@example.com'],
        ]));
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['alice@example.com', 'bob@example.com'], $body['data']['duplicates']);
    }

    /** Endpoint duplicates: payload privo della chiave "emails" → 422. */
    public function testDuplicatesEndpointRejectsBadInput(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/duplicates', [
            'wrong' => 'shape',
        ]));
        $this->assertSame(422, $response->getStatusCode());
    }

    /** Endpoint events: raggruppa per user_id e calcola last_event. */
    public function testEventsEndpoint(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/events', [
            'events' => [
                ['user_id' => 10, 'event' => 'login'],
                ['user_id' => 12, 'event' => 'logout'],
                ['user_id' => 10, 'event' => 'upload'],
            ],
        ]));
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $body['data']['groups']);
        $this->assertSame(10, $body['data']['groups'][0]['user_id']);
        $this->assertSame('upload', $body['data']['groups'][0]['last_event']);
    }

    /** Endpoint tree con operazione "leaves": ritorna le foglie dell'albero. */
    public function testTreeLeaves(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/tree', [
            'operation' => 'leaves',
            'tree' => [
                'name' => 'root',
                'children' => [
                    ['name' => 'A', 'children' => [['name' => 'A1', 'children' => []]]],
                    ['name' => 'B', 'children' => []],
                ],
            ],
        ]));
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['A1', 'B'], $body['data']['leaves']);
    }

    /** Endpoint tree con operazione "depth" su albero null: profondità 0. */
    public function testTreeDepthEmpty(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/tree', [
            'operation' => 'depth',
            'tree' => null,
        ]));
        $body = $this->jsonBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $body['data']['depth']);
    }

    /** Endpoint tree con operazione "path" e target inesistente: data.path è null. */
    public function testTreePathNotFound(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/tree', [
            'operation' => 'path',
            'target'    => 'ZZZ',
            'tree'      => ['name' => 'root', 'children' => []],
        ]));
        $body = $this->jsonBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($body['data']['path']);
    }

    /** Endpoint tree con operazione non riconosciuta: 422. */
    public function testTreeRejectsUnknownOperation(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->jsonRequest('POST', '/problem-solving/tree', [
            'operation' => 'eat',
            'tree'      => ['name' => 'r', 'children' => []],
        ]));
        $this->assertSame(422, $response->getStatusCode());
    }
}
