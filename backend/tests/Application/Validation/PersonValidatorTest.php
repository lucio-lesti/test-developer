<?php

declare(strict_types=1);

namespace Tests\Application\Validation;

use App\Application\Validation\PersonValidator;
use PHPUnit\Framework\TestCase;

/**
 * Test unitari del validator: nessuna pipeline HTTP, istanza diretta della classe.
 *
 * Copre sia la validazione completa (validateCreate) sia quella parziale (validatePatch),
 * usata anche dall'azione di import CSV per ogni riga.
 */
final class PersonValidatorTest extends TestCase
{
    private PersonValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PersonValidator();
    }

    /** Payload completo e valido: trim su nome, email in minuscolo, ruolo accettato dalla whitelist. */
    public function testValidPayloadIsAccepted(): void
    {
        $result = $this->validator->validateCreate([
            'first_name'    => '  Ada  ',
            'last_name'     => 'Lovelace',
            'email'         => 'Ada@Example.COM',
            'date_of_birth' => '1815-12-10',
            'phone_number'  => '+39 333 1234567',
            'role'          => 'admin',
            'notes'         => 'first programmer',
        ]);

        $this->assertTrue($result->isValid());
        $this->assertSame('Ada', $result->values()['first_name']);
        $this->assertSame('ada@example.com', $result->values()['email']);
        $this->assertSame('admin', $result->values()['role']);
    }

    /** Campi obbligatori (first_name, last_name, email) mancanti: errori per ciascuno. */
    public function testRequiredFieldsAreReportedWhenMissing(): void
    {
        $result = $this->validator->validateCreate([]);
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('first_name', $result->errors());
        $this->assertArrayHasKey('last_name', $result->errors());
        $this->assertArrayHasKey('email', $result->errors());
    }

    /** Stringhe vuote o composte solo da spazi sui campi obbligatori vengono rifiutate. */
    public function testBlankRequiredStringsAreRejected(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => '   ',
            'last_name'  => '',
            'email'      => '   ',
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['first_name']);
        $this->assertNotEmpty($result->errors()['last_name']);
        $this->assertNotEmpty($result->errors()['email']);
    }

    /** Email malformata: errore sul campo email. */
    public function testEmailMustBeValid(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'not-an-email',
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['email']);
    }

    /** Ruolo fuori whitelist (admin/user/moderator/guest): rifiutato. */
    public function testRoleMustBeWhitelisted(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'a@b.it',
            'role'       => 'superuser',
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['role']);
    }

    /** Nome oltre i 100 caratteri: rifiutato. */
    public function testNamesTooLongAreRejected(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => str_repeat('a', 101),
            'last_name'  => 'Lovelace',
            'email'      => 'a@b.it',
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['first_name']);
    }

    /** Data di nascita futura: rifiutata. */
    public function testFutureDateOfBirthIsRejected(): void
    {
        $future = (new \DateTimeImmutable('+10 years'))->format('Y-m-d');
        $result = $this->validator->validateCreate([
            'first_name'    => 'Ada',
            'last_name'     => 'Lovelace',
            'email'         => 'a@b.it',
            'date_of_birth' => $future,
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['date_of_birth']);
    }

    /** Data di calendario inesistente (es. 30 febbraio): rifiutata. */
    public function testInvalidCalendarDateIsRejected(): void
    {
        $result = $this->validator->validateCreate([
            'first_name'    => 'Ada',
            'last_name'     => 'Lovelace',
            'email'         => 'a@b.it',
            'date_of_birth' => '2021-02-30',
        ]);
        $this->assertFalse($result->isValid());
    }

    /** Campi non previsti dalla whitelist (es. is_admin, id): silenziosamente eliminati per evitare mass-assignment. */
    public function testUnexpectedFieldsAreStripped(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'a@b.it',
            'is_admin'   => true,
            'id'         => 999,
        ]);
        $this->assertTrue($result->isValid());
        $this->assertArrayNotHasKey('is_admin', $result->values());
        $this->assertArrayNotHasKey('id', $result->values());
    }

    /** validatePatch consente di omettere i campi obbligatori (logica usata dall'aggiornamento parziale e dall'import per riga). */
    public function testPatchAllowsMissingRequiredFields(): void
    {
        $result = $this->validator->validatePatch(['notes' => 'just a note']);
        $this->assertTrue($result->isValid());
        $this->assertSame('just a note', $result->values()['notes']);
    }

    /** validatePatch valida comunque i campi inviati (es. email malformata). */
    public function testPatchStillValidatesProvidedFields(): void
    {
        $result = $this->validator->validatePatch(['email' => 'bad']);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['email']);
    }

    /** Input non-array (es. stringa): errore sotto la chiave _body. */
    public function testNonArrayInputIsRejected(): void
    {
        $result = $this->validator->validateCreate('not-an-object');
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('_body', $result->errors());
    }

    /** Data di nascita esplicitamente null in PATCH: accettata e mantenuta a null. */
    public function testNullDateOfBirthIsAccepted(): void
    {
        $result = $this->validator->validatePatch(['date_of_birth' => null]);
        $this->assertTrue($result->isValid());
        $this->assertNull($result->values()['date_of_birth']);
    }

    /** Note oltre i 1000 caratteri: rifiutate. */
    public function testNotesTooLongRejected(): void
    {
        $result = $this->validator->validateCreate([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'a@b.it',
            'notes'      => str_repeat('x', 1001),
        ]);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->errors()['notes']);
    }
}
