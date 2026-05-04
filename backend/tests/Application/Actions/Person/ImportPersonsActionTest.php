<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Person;

use Slim\Psr7\UploadedFile;
use Tests\TestCase;

/**
 * Test di integrazione per l'endpoint POST /persons/import.
 *
 * Verifica caricamento CSV, validazione MIME magic-bytes, limite di
 * dimensione, validazione per riga e gestione duplicati.
 */
final class ImportPersonsActionTest extends TestCase
{
    /** @var array<int,string> Lista di file temporanei creati dai test, rimossi in tearDown. */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tempFiles = [];
    }

    /** CSV valido: tutte le righe vengono importate (total = valid, invalid = 0). */
    public function testValidCsvImportsAllRows(): void
    {
        $csv = "first_name,last_name,email,role\n"
             . "Ada,Lovelace,ada@example.com,admin\n"
             . "Bob,Builder,bob@example.com,user\n";

        $response = $this->uploadCsv($csv);
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $body['data']['total']);
        $this->assertSame(2, $body['data']['valid']);
        $this->assertSame(0, $body['data']['invalid']);
    }

    /** Righe non valide (campo vuoto, email malformata) vengono segnalate ma non bloccano il resto del file. */
    public function testCsvWithInvalidRowsIsReported(): void
    {
        $csv = "first_name,last_name,email\n"
             . "Ada,Lovelace,ada@example.com\n"
             . ",Smith,nobody@example.com\n"
             . "Eve,Doe,not-an-email\n";

        $response = $this->uploadCsv($csv);
        $body = $this->jsonBody($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $body['data']['total']);
        $this->assertSame(1, $body['data']['valid']);
        $this->assertSame(2, $body['data']['invalid']);
        $this->assertCount(2, $body['data']['errors']);
    }

    /** Email duplicata all'interno dello stesso file: solo la prima occorrenza importata, la seconda segnalata sul campo "email". */
    public function testCsvWithDuplicateEmailWithinFile(): void
    {
        $csv = "first_name,last_name,email\n"
             . "Ada,Lovelace,ada@example.com\n"
             . "Ada,Two,ADA@example.com\n";

        $response = $this->uploadCsv($csv);
        $body = $this->jsonBody($response);

        $this->assertSame(1, $body['data']['valid']);
        $this->assertSame(1, $body['data']['invalid']);
        $this->assertSame('email', array_key_first($body['data']['errors'][0]['fields']));
    }

    /** Tipo MIME non corrispondente al CSV (qui PNG): rifiuto 422 con errore sul campo "file". */
    public function testWrongMimeTypeRejected(): void
    {
        $bytes = "\x89PNG\r\n\x1a\nFAKEPNGDATA";
        $app = $this->getAppInstance();
        $request = $this->multipartUpload($bytes, 'image.png', 'image/png');
        $response = $app->handle($request);
        $body = $this->jsonBody($response);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('file', $body['data']['fields']);
    }

    /** File oltre il limite di dimensione: rifiuto 422 con errore sul campo "file". */
    public function testOversizedFileRejected(): void
    {
        // Genera ~7.7 MB di righe per superare il limite di 5 MB definito in settings.
        $csv = "first_name,last_name,email\n" . str_repeat("a,b,c@d.it\n", 700000);
        $app = $this->getAppInstance();

        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($tmp, $csv);
        $this->tempFiles[] = $tmp;

        $size = filesize($tmp);
        $uploaded = new UploadedFile($tmp, 'big.csv', 'text/csv', $size, UPLOAD_ERR_OK, false);
        $request = $this->createRequest('POST', '/persons/import')
            ->withUploadedFiles(['file' => $uploaded]);
        $response = $app->handle($request);

        $body = $this->jsonBody($response);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('file', $body['data']['fields']);
    }

    /** Campo "file" assente nella richiesta multipart: rifiuto 422 con errore sul campo "file". */
    public function testMissingFileField(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('POST', '/persons/import');
        $response = $app->handle($request);

        $this->assertSame(422, $response->getStatusCode());
        $body = $this->jsonBody($response);
        $this->assertArrayHasKey('file', $body['data']['fields']);
    }

    /** Intestazione CSV con colonna sconosciuta: nessuna riga importata, errore segnalato sotto la chiave "_header". */
    public function testHeaderWithUnknownColumnIsReported(): void
    {
        $csv = "first_name,last_name,email,is_admin\n"
             . "Ada,Lovelace,ada@example.com,true\n";
        $response = $this->uploadCsv($csv);
        $body = $this->jsonBody($response);
        $this->assertSame(0, $body['data']['valid']);
        $this->assertSame(0, $body['data']['invalid']);
        $this->assertNotEmpty($body['data']['errors']);
        $this->assertSame('_header', array_key_first($body['data']['errors'][0]['fields']));
    }

    private function uploadCsv(string $contents)
    {
        return $this->getAppInstance()->handle(
            $this->multipartUpload($contents, 'persons.csv', 'text/csv')
        );
    }

    private function multipartUpload(string $contents, string $filename, string $contentType)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($tmp, $contents);
        $this->tempFiles[] = $tmp;

        $uploaded = new UploadedFile($tmp, $filename, $contentType, filesize($tmp), UPLOAD_ERR_OK, false);

        return $this->createRequest('POST', '/persons/import')
            ->withUploadedFiles(['file' => $uploaded]);
    }
}
