<?php

declare(strict_types=1);

namespace App\Domain\ProblemSolving;

use InvalidArgumentException;

/**
 * B3 — Nested tree traversal.
 *
 * The input tree is an associative structure: { name: string, children: Node[] }.
 *
 * Three operations are supported:
 *   - "leaves" : list every leaf node name (depth-first, left-to-right)
 *   - "depth"  : maximum depth of the tree (root with no children = depth 1, empty tree = 0)
 *   - "path"   : path from root to a target node (by name); null if not found
 *
 * Approach: recursion with explicit base cases.
 *   - leaves: a node is a leaf when "children" is empty/missing.
 *   - depth : 1 + max(child depths); empty tree special-cased to 0.
 *   - path  : DFS, return early on first match. Path is the chain of names.
 *
 * Time complexity:  O(n)   (n = total nodes; visits each at most once)
 * Space complexity: O(h)   (h = tree height; recursion stack)
 *
 * Edge cases handled:
 * - empty tree (null) -> leaves: [], depth: 0, path: null
 * - missing/non-array "children" treated as no children
 * - path target equal to root -> single-element path
 * - target absent -> null
 * - non-string node names rejected with InvalidArgumentException at root
 */
final class TreeTraverser
{
    /**
     * @param mixed $tree
     * @return string[]
     */
    public function leaves($tree): array
    {
        if ($tree === null) {
            return [];
        }
        $this->assertNode($tree);
        $out = [];
        $this->collectLeaves($tree, $out);
        return $out;
    }

    /**
     * @param mixed $tree
     */
    public function depth($tree): int
    {
        if ($tree === null) {
            return 0;
        }
        $this->assertNode($tree);
        return $this->computeDepth($tree);
    }

    /**
     * @param mixed $tree
     * @return string[]|null
     */
    public function path($tree, string $target): ?array
    {
        if ($tree === null) {
            return null;
        }
        $this->assertNode($tree);
        $path = [];
        return $this->findPath($tree, $target, $path) ? $path : null;
    }

    /**
     * @param array<string,mixed> $node
     * @param string[]            $out
     */
    private function collectLeaves(array $node, array &$out): void
    {
        $children = $this->children($node);
        if ($children === []) {
            $out[] = (string) $node['name'];
            return;
        }
        foreach ($children as $child) {
            $this->collectLeaves($child, $out);
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    private function computeDepth(array $node): int
    {
        $children = $this->children($node);
        if ($children === []) {
            return 1;
        }
        $max = 0;
        foreach ($children as $child) {
            $d = $this->computeDepth($child);
            if ($d > $max) {
                $max = $d;
            }
        }
        return 1 + $max;
    }

    /**
     * @param array<string,mixed> $node
     * @param string[]            $path
     */
    private function findPath(array $node, string $target, array &$path): bool
    {
        $path[] = (string) $node['name'];

        if ($node['name'] === $target) {
            return true;
        }

        foreach ($this->children($node) as $child) {
            if ($this->findPath($child, $target, $path)) {
                return true;
            }
        }

        array_pop($path);
        return false;
    }

    /**
     * @param mixed $node
     * @return array<int,array<string,mixed>>
     */
    private function children($node): array
    {
        if (!is_array($node) || !isset($node['children']) || !is_array($node['children'])) {
            return [];
        }
        $out = [];
        foreach ($node['children'] as $child) {
            $this->assertNode($child);
            $out[] = $child;
        }
        return $out;
    }

    /**
     * @param mixed $node
     */
    private function assertNode($node): void
    {
        if (!is_array($node) || !isset($node['name']) || !is_string($node['name'])) {
            throw new InvalidArgumentException('Each node must be an object with a string "name" field.');
        }
    }
}
