<?php

declare(strict_types=1);

namespace Tests\Domain\ProblemSolving;

use App\Domain\ProblemSolving\EventGrouper;
use PHPUnit\Framework\TestCase;

/**
 * Test unitari per l'algoritmo B2 — raggruppamento eventi per user_id.
 *
 * Output atteso: un elemento per ogni user_id con lista eventi nell'ordine
 * di arrivo, totale, ultimo evento, ordinati per user_id crescente.
 */
final class EventGrouperTest extends TestCase
{
    private EventGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new EventGrouper();
    }

    /** Lista vuota: output vuoto. */
    public function testEmptyInput(): void
    {
        $this->assertSame([], $this->grouper->group([]));
    }

    /** Esempio della specifica: due utenti distinti, eventi raggruppati e contati. */
    public function testSpecExample(): void
    {
        $events = [
            ['user_id' => 10, 'event' => 'login'],
            ['user_id' => 12, 'event' => 'logout'],
            ['user_id' => 10, 'event' => 'upload'],
        ];

        $this->assertSame([
            ['user_id' => 10, 'events' => ['login', 'upload'], 'total' => 2, 'last_event' => 'upload'],
            ['user_id' => 12, 'events' => ['logout'],          'total' => 1, 'last_event' => 'logout'],
        ], $this->grouper->group($events));
    }

    /** Singolo utente con più eventi: ordine di arrivo conservato e last_event corretto. */
    public function testSingleUser(): void
    {
        $events = [
            ['user_id' => 7, 'event' => 'a'],
            ['user_id' => 7, 'event' => 'b'],
        ];
        $out = $this->grouper->group($events);
        $this->assertCount(1, $out);
        $this->assertSame(['a', 'b'], $out[0]['events']);
        $this->assertSame('b', $out[0]['last_event']);
    }

    /** Voci malformate (user_id non intero, campi mancanti, non-array): saltate silenziosamente. */
    public function testInvalidEntriesAreSkipped(): void
    {
        $events = [
            ['user_id' => 1, 'event' => 'ok'],
            ['user_id' => 'abc', 'event' => 'skip'],
            ['user_id' => 2],
            'not-an-array',
            ['event' => 'no-user'],
            ['user_id' => 3, 'event' => ''],
        ];
        $out = $this->grouper->group($events);
        $this->assertCount(1, $out);
        $this->assertSame(1, $out[0]['user_id']);
    }

    /** Output ordinato per user_id crescente, indipendentemente dall'ordine in input. */
    public function testOutputSortedByUserId(): void
    {
        $events = [
            ['user_id' => 30, 'event' => 'a'],
            ['user_id' => 1,  'event' => 'b'],
            ['user_id' => 5,  'event' => 'c'],
        ];
        $out = $this->grouper->group($events);
        $this->assertSame([1, 5, 30], array_column($out, 'user_id'));
    }
}
