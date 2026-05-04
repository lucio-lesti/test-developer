<?php

declare(strict_types=1);

namespace Tests\Domain\ProblemSolving;

use App\Domain\ProblemSolving\TreeTraverser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Test unitari per l'algoritmo B3 — visita ricorsiva di alberi annidati.
 *
 * Operazioni testate: leaves (foglie), depth (profondità massima),
 * path (percorso radice → nodo target).
 */
final class TreeTraverserTest extends TestCase
{
    private TreeTraverser $t;

    protected function setUp(): void
    {
        $this->t = new TreeTraverser();
    }

    /** Albero di esempio condiviso dai test. */
    private function sampleTree(): array
    {
        return [
            'name' => 'root',
            'children' => [
                ['name' => 'A', 'children' => [
                    ['name' => 'A1', 'children' => []],
                ]],
                ['name' => 'B', 'children' => []],
            ],
        ];
    }

    /** Albero null: leaves [], depth 0, path null. Casi limite di base. */
    public function testEmptyTree(): void
    {
        $this->assertSame([], $this->t->leaves(null));
        $this->assertSame(0, $this->t->depth(null));
        $this->assertNull($this->t->path(null, 'anything'));
    }

    /** Singolo nodo (radice senza figli): è esso stesso una foglia, profondità 1, path == [name]. */
    public function testSingleNode(): void
    {
        $tree = ['name' => 'only', 'children' => []];
        $this->assertSame(['only'], $this->t->leaves($tree));
        $this->assertSame(1, $this->t->depth($tree));
        $this->assertSame(['only'], $this->t->path($tree, 'only'));
        $this->assertNull($this->t->path($tree, 'missing'));
    }

    /** Foglie dell'albero di esempio: i nodi senza figli (A1, B). */
    public function testLeavesOnSampleTree(): void
    {
        $this->assertSame(['A1', 'B'], $this->t->leaves($this->sampleTree()));
    }

    /** Profondità massima dell'albero di esempio: 3 livelli (root → A → A1). */
    public function testDepthOnSampleTree(): void
    {
        $this->assertSame(3, $this->t->depth($this->sampleTree()));
    }

    /** Percorso radice → target. Restituisce null se il target non è nell'albero. */
    public function testPathOnSampleTree(): void
    {
        $this->assertSame(['root', 'A', 'A1'], $this->t->path($this->sampleTree(), 'A1'));
        $this->assertSame(['root', 'B'], $this->t->path($this->sampleTree(), 'B'));
        $this->assertSame(['root'], $this->t->path($this->sampleTree(), 'root'));
        $this->assertNull($this->t->path($this->sampleTree(), 'unknown'));
    }

    /** Nodo senza chiave "children": trattato come foglia (children implicito = []). */
    public function testMissingChildrenTreatedAsLeaf(): void
    {
        $tree = ['name' => 'r', 'children' => [['name' => 'x']]];
        $this->assertSame(['x'], $this->t->leaves($tree));
        $this->assertSame(2, $this->t->depth($tree));
    }

    /** Nodo malformato (senza chiave "name"): InvalidArgumentException. */
    public function testInvalidNodeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->t->leaves(['no_name' => true]);
    }
}
