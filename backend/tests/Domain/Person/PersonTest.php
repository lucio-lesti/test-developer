<?php

declare(strict_types=1);

namespace Tests\Domain\Person;

use App\Domain\Person\Person;
use PHPUnit\Framework\TestCase;

/**
 * Test unitari dell'entità di dominio Person.
 *
 * Verificano i contratti immutabili dell'entità: normalizzazione email,
 * comportamento di withId() come copia immutabile, forma serializzata.
 */
final class PersonTest extends TestCase
{
    /** L'email viene sempre normalizzata in minuscolo dal costruttore. */
    public function testEmailIsLowercased(): void
    {
        $person = new Person(null, 'Ada', 'Lovelace', 'Ada@Example.COM');
        $this->assertSame('ada@example.com', $person->getEmail());
    }

    /** withId() restituisce una nuova istanza con id assegnato, senza modificare l'originale (immutabilità). */
    public function testWithIdReturnsNewInstance(): void
    {
        $person = new Person(null, 'Ada', 'Lovelace', 'ada@example.com');
        $copy = $person->withId(7);

        $this->assertNull($person->getId());
        $this->assertSame(7, $copy->getId());
        $this->assertNotSame($person, $copy);
    }

    /** jsonSerialize() restituisce la rappresentazione canonica con tutte le chiavi previste. */
    public function testJsonSerializeShape(): void
    {
        $person = new Person(1, 'Ada', 'Lovelace', 'ada@example.com', '1815-12-10', null, 'admin', null);
        $this->assertSame(
            [
                'id'            => 1,
                'first_name'    => 'Ada',
                'last_name'     => 'Lovelace',
                'email'         => 'ada@example.com',
                'date_of_birth' => '1815-12-10',
                'phone_number'  => null,
                'role'          => 'admin',
                'notes'         => null,
            ],
            $person->jsonSerialize()
        );
    }
}
