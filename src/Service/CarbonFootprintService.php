<?php

declare(strict_types=1);

namespace App\Service;

class CarbonFootprintService
{
    private const EMISSION_FACTOR = 0.255; // kg CO2 per km per person

    /** @var array<string, int> */
    private const DISTANCES = [
        'paris'   => 1820,
        'london'  => 2100,
        'rome'    => 1700,
        'madrid'  => 2300,
        'berlin'  => 2400,
        'amsterdam' => 2200,
        'vienna'  => 2000,
        'prague'  => 2150,
        'lisbon'  => 2600,
        'athens'  => 2500,
        'malta'   => 290,
        'sydney'  => 16500,
        'dubai'   => 5200,
        'tokyo'   => 9500,
        'new york' => 7000,
        'montreal' => 6700,
    ];

    private const DEFAULT_DISTANCE = 2500;

    public function getDistance(string $destination): int
    {
        $key = strtolower($destination);
        foreach (self::DISTANCES as $city => $km) {
            if (str_contains($key, $city)) {
                return $km;
            }
        }
        return self::DEFAULT_DISTANCE;
    }

    /**
     * @return array{distance_km: int, co2_per_person: float, total_co2: float, badge: string, color: string, label: string}
     */
    public function calculate(string $destination, int $people): array
    {
        $distance = $this->getDistance($destination);
        $co2PerPerson = round($distance * self::EMISSION_FACTOR, 2);
        $totalCo2 = round($co2PerPerson * $people, 2);

        [$label, $badge, $color] = $this->classify($co2PerPerson);

        return [
            'distance_km'    => $distance,
            'co2_per_person' => $co2PerPerson,
            'total_co2'      => $totalCo2,
            'badge'          => $badge,
            'color'          => $color,
            'label'          => $label,
        ];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function classify(float $co2PerPerson): array
    {
        if ($co2PerPerson < 200) {
            return ['Eco-friendly', '🌿', 'green'];
        }
        if ($co2PerPerson < 1000) {
            return ['Moderate', '🌍', 'orange'];
        }
        return ['High impact', '🔥', 'red'];
    }
}
