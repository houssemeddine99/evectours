<?php

namespace App\Service;

use App\Repository\ReservationRepository;

class DynamicPricingService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {}

    /**
     * @return array{price: float, base_price: float, scarcity_label: string, scarcity_level: string, booked: int}
     *   scarcity_level: 'none' | 'low' | 'medium' | 'high' | 'urgent'
     */
    public function calculate(float $basePrice, int $voyageId, ?\DateTimeInterface $departureDate): array
    {
        $booked = $this->reservationRepository->sumBookedPeopleByVoyageId($voyageId);

        $demandPct = $this->demandFactor($booked);
        $timePct   = $this->timeFactor($departureDate);
        $surchargePct = min(50, $demandPct + $timePct);

        $adjustedPrice = round($basePrice * (1 + $surchargePct / 100), 2);

        [$level, $label] = $this->scarcity($booked, $departureDate);

        return [
            'price'           => $adjustedPrice,
            'base_price'      => $basePrice,
            'scarcity_label'  => $label,
            'scarcity_level'  => $level,
            'booked'          => $booked,
        ];
    }

    private function demandFactor(int $booked): int
    {
        return match (true) {
            $booked >= 30 => 25,
            $booked >= 15 => 15,
            $booked >= 6  => 8,
            default       => 0,
        };
    }

    private function timeFactor(?\DateTimeInterface $departureDate): int
    {
        if ($departureDate === null) {
            return 0;
        }
        $now = new \DateTime();
        if ($now >= $departureDate) {
            return 0;
        }
        $daysUntil = (int) $now->diff($departureDate)->days;

        return match (true) {
            $daysUntil <= 7  => 20,
            $daysUntil <= 14 => 12,
            $daysUntil <= 30 => 6,
            $daysUntil <= 60 => 3,
            default          => 0,
        };
    }

    /** @return array{string, string} [level, label] */
    private function scarcity(int $booked, ?\DateTimeInterface $departureDate): array
    {
        $daysUntil = null;
        if ($departureDate !== null) {
            $now = new \DateTime();
            $daysUntil = $now < $departureDate ? (int) $now->diff($departureDate)->days : 0;
        }

        // Urgency: last-minute departure beats everything
        if ($daysUntil !== null && $daysUntil <= 3 && $daysUntil > 0) {
            return ['urgent', '⚡ Only ' . $daysUntil . ' day' . ($daysUntil > 1 ? 's' : '') . ' left to book!'];
        }
        if ($daysUntil !== null && $daysUntil <= 7 && $daysUntil > 0) {
            return ['urgent', '⚡ Departing in ' . $daysUntil . ' days — book now'];
        }

        // Demand-based scarcity
        if ($booked >= 30) {
            return ['high', '🔴 Almost fully booked'];
        }
        if ($booked >= 15) {
            return ['medium', '🟠 Spots filling up fast'];
        }
        if ($booked >= 6) {
            return ['low', '🟡 Popular — book soon'];
        }

        return ['none', ''];
    }
}
