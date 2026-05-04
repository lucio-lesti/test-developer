<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Person;

use Tests\TestCase;

/**
 * Test di integrazione per gli endpoint /persons.
 *
 * I test passano per la pipeline Slim ($app->handle) e verificano
 * stato HTTP, forma del payload JSON e codici di errore. Non dipendono
 * dalla classe Action (single-action o controller), ma solo dal routing
 * configurato in app/routes.php.
 */
final class PersonActionTest extends TestCase
{
    /** Creazione persona con payload valido: stato 201, email normalizzata in minuscolo, id intero assegnato. */
    public function testCreatePersonSuccess(): void
    {
        $app = $this->getAppInstance();
        $request = $this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'Ada@Example.COM',
            'role'       => 'admin',
        ]);

        $response = $app->handle($request);
        $body = $this->jsonBody($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('ada@example.com', $body['data']['email']);
        $this->assertSame('admin', $body['data']['role']);
        $this->assertIsInt($body['data']['id']);
    }

    /** Payload non valido: stato 422, tipo errore VALIDATION_ERROR e mappa errori per campo obbligatorio mancante o malformato. */
    public function testCreatePersonRejectsInvalidPayload(): void
    {
        $app = $this->getAppInstance();
        $request = $this->jsonRequest('POST', '/persons', [
            'first_name' => '',
            'email'      => 'bad',
        ]);

        $response = $app->handle($request);
        $body = $this->jsonBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('VALIDATION_ERROR', $body['error']['type']);
        $this->assertArrayHasKey('first_name', $body['data']['fields']);
        $this->assertArrayHasKey('last_name', $body['data']['fields']);
        $this->assertArrayHasKey('email', $body['data']['fields']);
    }

    /** Email duplicata: la seconda creazione con lo stesso indirizzo restituisce stato 409 (Conflict). */
    public function testCreateRejectsDuplicateEmailWith409(): void
    {
        $app = $this->getAppInstance();
        $payload = [
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'ada@example.com',
        ];
        $app->handle($this->jsonRequest('POST', '/persons', $payload));

        $response = $app->handle($this->jsonRequest('POST', '/persons', $payload));
        $this->assertSame(409, $response->getStatusCode());
    }

    /** GET /persons restituisce l'elenco completo delle persone create nella sessione di test. */
    public function testListPersons(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'a@a.it',
        ]));
        $app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Bob', 'last_name' => 'Builder',  'email' => 'b@b.it',
        ]));

        $response = $app->handle($this->createRequest('GET', '/persons'));
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $body['data']);
    }

    /** Filtri e ordinamento: ?role=admin restituisce solo gli amministratori; un valore "sort" sconosciuto non deve causare SQL injection né errore 500. */
    public function testListSortAndQueryFilter(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'a@a.it', 'role' => 'admin',
        ]));
        $app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Bob', 'last_name' => 'Builder', 'email' => 'b@b.it', 'role' => 'user',
        ]));

        $response = $app->handle($this->createRequest('GET', '/persons?role=admin'));
        $body = $this->jsonBody($response);
        $this->assertCount(1, $body['data']);
        $this->assertSame('a@a.it', $body['data'][0]['email']);

        // Una colonna di ordinamento sconosciuta deve essere ignorata silenziosamente (nessun vettore di SQL injection).
        $response2 = $app->handle($this->createRequest('GET', '/persons?sort=DROP%20TABLE&order=desc'));
        $this->assertSame(200, $response2->getStatusCode());
    }

    /** GET /persons/{id}: restituisce il dettaglio della persona richiesta. */
    public function testViewPerson(): void
    {
        $app = $this->getAppInstance();
        $created = $this->jsonBody($app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'a@a.it',
        ])));
        $id = $created['data']['id'];

        $response = $app->handle($this->createRequest('GET', "/persons/$id"));
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('a@a.it', $body['data']['email']);
    }

    /** GET /persons/{id} per id inesistente: stato 404. */
    public function testViewPersonNotFound(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/persons/9999'));
        $this->assertSame(404, $response->getStatusCode());
    }

    /** PUT /persons/{id}: aggiornamento completo. I campi opzionali non inviati vengono azzerati a null. */
    public function testPutFullUpdate(): void
    {
        $app = $this->getAppInstance();
        $created = $this->jsonBody($app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'a@a.it', 'role' => 'guest',
        ])));
        $id = $created['data']['id'];

        $response = $app->handle($this->jsonRequest('PUT', "/persons/$id", [
            'first_name' => 'Augusta',
            'last_name'  => 'Byron',
            'email'      => 'augusta@example.com',
        ]));
        $body = $this->jsonBody($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Augusta', $body['data']['first_name']);
        $this->assertSame('augusta@example.com', $body['data']['email']);
        $this->assertNull($body['data']['role']);
    }

    /** DELETE /persons/{id}: stato 204 alla cancellazione, 404 alla lettura successiva. */
    public function testDeletePerson(): void
    {
        $app = $this->getAppInstance();
        $created = $this->jsonBody($app->handle($this->jsonRequest('POST', '/persons', [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'a@a.it',
        ])));
        $id = $created['data']['id'];

        $response = $app->handle($this->createRequest('DELETE', "/persons/$id"));
        $this->assertSame(204, $response->getStatusCode());

        $miss = $app->handle($this->createRequest('GET', "/persons/$id"));
        $this->assertSame(404, $miss->getStatusCode());
    }
}
