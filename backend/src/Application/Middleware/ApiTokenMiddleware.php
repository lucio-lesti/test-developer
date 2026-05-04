<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Application\Settings\SettingsInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware di autenticazione tramite token API condiviso.
 *
 * Il token atteso è derivato dal segreto in settings.php tramite
 * sodium_crypto_generichash (BLAKE2b, 32 byte) ed è confrontato con
 * il valore inviato dal client nell'header X-API-Token (configurabile).
 * Il confronto è in tempo costante (hash_equals) per evitare timing attack.
 *
 * Le richieste OPTIONS (CORS preflight) attraversano il middleware
 * senza controllo, perché i browser non possono includere header
 * personalizzati nella preflight.
 */
final class ApiTokenMiddleware implements MiddlewareInterface
{
    private string $expected;
    private string $headerName;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(SettingsInterface $settings, ResponseFactoryInterface $responseFactory)
    {
        $tokenSettings = (array) $settings->get('token');
        $secret = (string) ($tokenSettings['secret'] ?? '');
        if ($secret === '') {
            throw new \RuntimeException('Token secret non configurato in settings.php (chiave: token.secret).');
        }
        $this->expected = bin2hex(sodium_crypto_generichash($secret));
        $this->headerName = (string) ($tokenSettings['header'] ?? 'X-API-Token');
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $handler->handle($request);
        }

        $provided = $request->getHeaderLine($this->headerName);
        if ($provided === '' || !hash_equals($this->expected, $provided)) {
            return $this->unauthorized();
        }

        return $handler->handle($request);
    }

    private function unauthorized(): ResponseInterface
    {
        $error = new ActionError(ActionError::UNAUTHENTICATED, 'Token API mancante o non valido.');
        $payload = new ActionPayload(401, null, $error);
        $response = $this->responseFactory->createResponse(401);
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
