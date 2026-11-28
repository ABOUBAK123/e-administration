<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Dates d'une réunion récurrente : chaque date devient une réunion à part entière.
 */
class MeetingRecurrence
{
    public const LABELS = [
        'daily'   => 'quotidienne',
        'weekly'  => 'hebdomadaire',
        'monthly' => 'mensuelle',
        'yearly'  => 'annuelle',
    ];

    /** Dernière date de fin acceptée : un an après le début, cinq ans pour une réunion annuelle. */
    public function maximumEnd(CarbonInterface $start, string $type): CarbonInterface
    {
        return $start->copy()->addYearsNoOverflow($type === 'yearly' ? 5 : 1)->endOfDay();
    }

    /**
     * Créneaux de la série, du début jusqu'à la date de fin incluse. Les mois et années sont comptés
     * depuis la première date : une réunion du 31 retombe sur le 31 après un mois plus court.
     *
     * @return list<array{0: CarbonInterface, 1: CarbonInterface}> [début, fin]
     */
    public function slots(CarbonInterface $start, CarbonInterface $end, string $type, ?CarbonInterface $until): array
    {
        if (!isset(self::LABELS[$type]) || !$until) {
            return [[$start, $end]];
        }

        $duration = (int) abs($end->getTimestamp() - $start->getTimestamp());
        $last = $until->copy()->endOfDay();
        $slots = [];

        for ($i = 0; ; $i++) {
            $slotStart = match ($type) {
                'daily'   => $start->copy()->addDays($i),
                'weekly'  => $start->copy()->addWeeks($i),
                'monthly' => $start->copy()->addMonthsNoOverflow($i),
                'yearly'  => $start->copy()->addYearsNoOverflow($i),
            };
            if ($slotStart->gt($last)) {
                return $slots;
            }
            $slots[] = [$slotStart, $slotStart->copy()->addSeconds($duration)];
        }
    }
}
