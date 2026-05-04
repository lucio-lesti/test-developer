<?php

declare(strict_types=1);

namespace App\Domain\ProblemSolving;

/**
 * B2 — Event grouping/aggregation.
 *
 * Approach: single pass building an associative map keyed by user_id.
 * For each user we keep an ordered list of events and remember the last one
 * (the most recently observed in input order). Output is sorted by user_id.
 *
 * Time complexity:  O(n + u log u)   (n events, u unique users; sort dominates u log u)
 * Space complexity: O(n + u)
 *
 * Edge cases handled:
 * - empty event list
 * - missing or non-int user_id  -> entry skipped
 * - missing or non-string event -> entry skipped
 * - single user / single event
 */
final class EventGrouper
{
    /**
     * @param array<int,mixed> $events
     * @return array<int,array{user_id:int, events:string[], total:int, last_event:string}>
     */
    public function group(array $events): array
    {
        $byUser = [];

        foreach ($events as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (!array_key_exists('user_id', $entry) || !array_key_exists('event', $entry)) {
                continue;
            }
            $userId = $entry['user_id'];
            $event  = $entry['event'];
            if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
                continue;
            }
            if (!is_string($event) || $event === '') {
                continue;
            }

            $userId = (int) $userId;
            if (!isset($byUser[$userId])) {
                $byUser[$userId] = ['events' => [], 'last' => $event];
            }
            $byUser[$userId]['events'][] = $event;
            $byUser[$userId]['last'] = $event;
        }

        ksort($byUser, SORT_NUMERIC);

        $output = [];
        foreach ($byUser as $userId => $bucket) {
            $output[] = [
                'user_id'    => $userId,
                'events'     => $bucket['events'],
                'total'      => count($bucket['events']),
                'last_event' => $bucket['last'],
            ];
        }
        return $output;
    }
}
