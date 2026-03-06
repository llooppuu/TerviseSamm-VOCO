<?php
declare(strict_types=1);

namespace App\Services;

final class TrendService
{
    /**
     * Arvutab trendi staatuse plaan.md §10.1–10.2 järgi.
     * @return 'green'|'yellow'|'red'|'missing'
     */
    public function getTrendStatus(?string $lastEntryDate, ?int $lastPushups, ?int $prevPushups, string $today): string
    {
        $missingThreshold = date('Y-m-d', strtotime($today . ' -14 days'));

        if ($lastEntryDate === null || $lastEntryDate < $missingThreshold) {
            return 'missing';
        }

        if ($lastPushups === null || $prevPushups === null) {
            return 'yellow';
        }

        $delta = $lastPushups - $prevPushups;
        if ($delta >= 2) return 'green';
        if ($delta <= -2) return 'red';
        return 'yellow';
    }
}
