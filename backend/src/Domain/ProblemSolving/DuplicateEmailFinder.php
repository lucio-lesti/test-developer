<?php

declare(strict_types=1);

namespace App\Domain\ProblemSolving;

/**
 * B1 — Duplicate email detection with normalization.
 *
 * Approach: single pass with a hash map. Each email is normalized
 * (trim + lowercase) and counted; emails with count >= 2 are returned
 * once each, preserving the first-seen-as-duplicate order.
 *
 * Time complexity:  O(n)            (n = number of input emails)
 * Space complexity: O(u)            (u = number of unique normalized emails)
 *
 * Edge cases handled:
 * - empty input
 * - mixed casing / surrounding whitespace
 * - non-string entries (skipped, do not crash)
 * - blank strings after normalization (skipped)
 */
final class DuplicateEmailFinder
{
    /**
     * @param array<int,mixed> $emails
     * @return string[] normalized emails that appear two or more times
     */
    public function findDuplicates(array $emails): array
    {
        $counts = [];
        $duplicates = [];

        foreach ($emails as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $normalized = strtolower(trim($raw));
            if ($normalized === '') {
                continue;
            }

            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
            if ($counts[$normalized] === 2) {
                $duplicates[] = $normalized;
            }
        }

        return $duplicates;
    }
}
