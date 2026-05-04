<?php

declare(strict_types=1);

namespace App\Application\Actions\Person;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Application\Settings\SettingsInterface;
use App\Application\Validation\PersonValidator;
use App\Domain\DomainException\DomainRecordNotFoundException;
use App\Domain\Person\Person;
use App\Domain\Person\PersonAlreadyExistsException;
use App\Domain\Person\PersonRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Slim\Exception\HttpNotFoundException;

/**
 * Controller in stile Laravel che raggruppa tutti gli endpoint /persons.
 *
 * Il routing attivo in app/routes.php usa una classe Action per endpoint (vedi CLAUDE.md).
 * Il wiring in stile controller è presente in routes.php ma commentato — per passare
 * a questo controller, decommentare quelle righe e commentare quelle single-action.
 *
 * I nomi dei metodi seguono la convenzione resourceful di Laravel:
 *     index / show / store / update / destroy / import
 */
final class PersonController
{
    private LoggerInterface $logger;
    private PersonRepository $personRepository;
    private PersonValidator $validator;

    /** @var array<string,mixed> */
    private array $uploadSettings;

    public function __construct(
        LoggerInterface $logger,
        PersonRepository $personRepository,
        PersonValidator $validator,
        SettingsInterface $settings
    ) {
        $this->logger = $logger;
        $this->personRepository = $personRepository;
        $this->validator = $validator;
        $this->uploadSettings = $settings->get('uploads');
    }

    // ---------------------------------------------------------------------
    // Azioni resourceful
    // ---------------------------------------------------------------------

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        $criteria = array_filter([
            'role'   => isset($query['role'])  ? (string) $query['role']  : null,
            'q'      => isset($query['q'])     ? (string) $query['q']     : null,
            'sort'   => isset($query['sort'])  ? (string) $query['sort']  : null,
            'order'  => isset($query['order']) ? (string) $query['order'] : null,
            'limit'  => $query['limit']  ?? null,
            'offset' => $query['offset'] ?? null,
        ], fn($v) => $v !== null);

        $persons = $this->personRepository->findAll($criteria);
        $this->logger->info('Persons list accessed.', ['count' => count($persons)]);

        return $this->ok($response, $persons);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $personId = (int) $args['id'];
        try {
            $person = $this->personRepository->findPersonOfId($personId);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($request);
        }

        $this->logger->info('Person viewed.', ['id' => $personId]);
        return $this->ok($response, $person);
    }

    public function store(Request $request, Response $response): Response
    {
        $result = $this->validator->validateCreate($request->getParsedBody());
        if (!$result->isValid()) {
            return $this->validationErrors($response, $result->errors());
        }

        $values = $result->values();

        if ($this->personRepository->findByEmail($values['email']) !== null) {
            return $this->conflict($response, 'Esiste già una persona con questa email.');
        }

        try {
            $saved = $this->personRepository->save($this->buildPerson($values));
        } catch (PersonAlreadyExistsException $e) {
            return $this->conflict($response, 'Esiste già una persona con questa email.');
        }

        $this->logger->info('Person created.', ['id' => $saved->getId()]);
        return $this->ok($response, $saved, 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $personId = (int) $args['id'];
        try {
            $this->personRepository->findPersonOfId($personId);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($request);
        }

        $result = $this->validator->validateCreate($request->getParsedBody());
        if (!$result->isValid()) {
            return $this->validationErrors($response, $result->errors());
        }

        $values = $result->values();

        $duplicate = $this->personRepository->findByEmail($values['email']);
        if ($duplicate !== null && $duplicate->getId() !== $personId) {
            return $this->conflict($response, 'Esiste già una persona con questa email.');
        }

        try {
            $saved = $this->personRepository->update($this->buildPerson($values, $personId));
        } catch (PersonAlreadyExistsException $e) {
            return $this->conflict($response, 'Esiste già una persona con questa email.');
        }

        $this->logger->info('Person updated (PUT).', ['id' => $personId]);
        return $this->ok($response, $saved);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $personId = (int) $args['id'];
        try {
            $this->personRepository->delete($personId);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($request);
        }

        $this->logger->info('Person deleted.', ['id' => $personId]);
        return $response->withStatus(204);
    }

    public function import(Request $request, Response $response): Response
    {
        $uploaded = $request->getUploadedFiles()['file'] ?? null;
        if (!$uploaded instanceof UploadedFileInterface) {
            return $this->validationErrors($response, ['file' => ['Campo "file" obbligatorio mancante.']]);
        }
        if ($uploaded->getError() !== UPLOAD_ERR_OK) {
            return $this->validationErrors($response, ['file' => ['Caricamento fallito.']]);
        }

        $maxBytes = (int) $this->uploadSettings['maxBytes'];
        if ($uploaded->getSize() !== null && $uploaded->getSize() > $maxBytes) {
            return $this->validationErrors($response, ['file' => ['File troppo grande (massimo ' . $maxBytes . ' byte).']]);
        }

        if (!in_array($this->sniffMime($uploaded), (array) $this->uploadSettings['allowedMimes'], true)) {
            return $this->validationErrors($response, ['file' => ['Tipo file non valido.']]);
        }

        $uploadDir = (string) $this->uploadSettings['dir'];
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->logger->error('Upload directory not writable.', ['dir' => $uploadDir]);
            throw new RuntimeException('Directory di upload non disponibile.');
        }

        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.csv';
        $uploaded->moveTo($targetPath);

        try {
            $size = filesize($targetPath);
            if ($size === false || $size > $maxBytes) {
                return $this->validationErrors($response, ['file' => ['File troppo grande.']]);
            }
            $summary = $this->processCsv($targetPath);
        } finally {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
        }

        $this->logger->info('CSV import completed.', [
            'total' => $summary['total'], 'valid' => $summary['valid'], 'invalid' => $summary['invalid'],
        ]);

        return $this->ok($response, $summary);
    }

    // ---------------------------------------------------------------------
    // Helper privati (normalmente vivrebbero in un Controller base Laravel / trait)
    // ---------------------------------------------------------------------

    /** @param array<string,mixed> $values */
    private function buildPerson(array $values, ?int $id = null): Person
    {
        return new Person(
            $id,
            $values['first_name'],
            $values['last_name'],
            $values['email'],
            $values['date_of_birth'] ?? null,
            $values['phone_number']  ?? null,
            $values['role']          ?? null,
            $values['notes']         ?? null
        );
    }

    /**
     * @param mixed $data
     */
    private function ok(Response $response, $data, int $status = 200): Response
    {
        $payload = new ActionPayload($status, $data);
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** @param array<string,string[]> $errors */
    private function validationErrors(Response $response, array $errors): Response
    {
        $error = new ActionError(ActionError::VALIDATION_ERROR, 'Dati non validi.');
        $payload = new ActionPayload(422, ['fields' => $errors], $error);
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
    }

    private function conflict(Response $response, string $description): Response
    {
        $error = new ActionError(ActionError::VERIFICATION_ERROR, $description);
        $payload = new ActionPayload(409, null, $error);
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
    }

    private function sniffMime(UploadedFileInterface $uploaded): string
    {
        $stream = $uploaded->getStream();
        $stream->rewind();
        $head = $stream->read(4096);
        $stream->rewind();

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($head);
        return is_string($mime) ? $mime : '';
    }

    /**
     * @return array{total:int, valid:int, invalid:int, errors:array<int,array{row:int, fields:array<string,string[]>}>}
     */
    private function processCsv(string $path): array
    {
        $summary = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'errors' => []];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Impossibile aprire il file caricato.');
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',');
            if ($headerRow === false || $headerRow === [null]) {
                return ['total' => 0, 'valid' => 0, 'invalid' => 0, 'errors' => [
                    ['row' => 1, 'fields' => ['_header' => ['Riga di intestazione mancante.']]],
                ]];
            }
            $headers = array_map(static fn($h) => strtolower(trim((string) $h)), $headerRow);
            $allowed = (array) $this->uploadSettings['allowedHeaders'];
            $unknown = array_diff($headers, $allowed);
            $missingRequired = array_diff(['first_name', 'last_name', 'email'], $headers);

            if ($unknown !== [] || $missingRequired !== []) {
                $msgs = [];
                if ($unknown !== [])         { $msgs[] = 'Colonne sconosciute: ' . implode(', ', $unknown); }
                if ($missingRequired !== []) { $msgs[] = 'Colonne obbligatorie mancanti: ' . implode(', ', $missingRequired); }
                return ['total' => 0, 'valid' => 0, 'invalid' => 0, 'errors' => [
                    ['row' => 1, 'fields' => ['_header' => $msgs]],
                ]];
            }

            $rowNumber = 1;
            $seenEmails = [];

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }

                $summary['total']++;

                if (count($row) !== count($headers)) {
                    $summary['invalid']++;
                    $summary['errors'][] = ['row' => $rowNumber, 'fields' => ['_row' => ['Numero di colonne non corrispondente.']]];
                    continue;
                }

                $assoc = array_combine($headers, $row);
                $result = $this->validator->validateCreate($assoc);
                if (!$result->isValid()) {
                    $summary['invalid']++;
                    $summary['errors'][] = ['row' => $rowNumber, 'fields' => $result->errors()];
                    continue;
                }

                $values = $result->values();
                $email = $values['email'];

                if (isset($seenEmails[$email])) {
                    $summary['invalid']++;
                    $summary['errors'][] = ['row' => $rowNumber, 'fields' => ['email' => ['Email duplicata nel file.']]];
                    continue;
                }

                try {
                    $this->personRepository->save($this->buildPerson($values));
                    $seenEmails[$email] = true;
                    $summary['valid']++;
                } catch (PersonAlreadyExistsException $e) {
                    $summary['invalid']++;
                    $summary['errors'][] = ['row' => $rowNumber, 'fields' => ['email' => ['Email già presente nel database.']]];
                }
            }
        } finally {
            fclose($handle);
        }

        return $summary;
    }
}
