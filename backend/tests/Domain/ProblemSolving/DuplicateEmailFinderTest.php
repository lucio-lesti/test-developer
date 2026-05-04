<?php

declare(strict_types=1);

namespace Tests\Domain\ProblemSolving;

use App\Domain\ProblemSolving\DuplicateEmailFinder;
use PHPUnit\Framework\TestCase;

/**
 * Test unitari per l'algoritmo B1 — rilevamento email duplicate.
 *
 * Specifica: normalizzazione (trim + lowercase) e singolo passaggio
 * con hash map → complessità O(n) tempo, O(n) spazio.
 */
final class DuplicateEmailFinderTest extends TestCase
{
    private DuplicateEmailFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new DuplicateEmailFinder();
    }

    /** Esempio della specifica: trim + lowercase, output ordinato e deduplicato. */
    public function testSpecExample(): void
    {
        $input = [' Alice@example.com ', 'bob@example.com', 'alice@example.com', 'BOB@example.com', 'carol@example.com'];
        $this->assertSame(['alice@example.com', 'bob@example.com'], $this->finder->findDuplicates($input));
    }

    /** Lista vuota in input: lista vuota in output. */
    public function testEmptyInput(): void
    {
        $this->assertSame([], $this->finder->findDuplicates([]));
    }

    /** Nessun duplicato: array vuoto. */
    public function testNoDuplicates(): void
    {
        $this->assertSame([], $this->finder->findDuplicates(['a@a.it', 'b@b.it']));
    }

    /** Tripla occorrenza della stessa email: appare una sola volta nei duplicati. */
    public function testTripleOccurrenceCountedOnce(): void
    {
        $this->assertSame(['a@a.it'], $this->finder->findDuplicates(['a@a.it', 'a@a.it', 'A@A.it']));
    }

    /** Voci non-stringa (null, numeri) vengono ignorate, non causano errore. */
    public function testNonStringEntriesIgnored(): void
    {
        $this->assertSame(['a@a.it'], $this->finder->findDuplicates(['a@a.it', null, 42, 'a@a.it']));
    }

    /** Stringhe vuote o di soli spazi: ignorate. */
    public function testBlankStringsIgnored(): void
    {
        $this->assertSame([], $this->finder->findDuplicates(['', '   ', '']));
    }
}
