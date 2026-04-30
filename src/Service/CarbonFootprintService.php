<?php

namespace App\Service;

class CarbonFootprintService
{
    // Distance from Tunis (km) to major destinations
    private const DISTANCES = [
        'paris'        => 1820,
        'london'       => 2100,
        'rome'         => 1700,
        'madrid'       => 1700,
        'barcelona'    => 1590,
        'berlin'       => 2300,
        'amsterdam'    => 2200,
        'brussels'     => 2100,
        'vienna'       => 2200,
        'zurich'       => 1900,
        'dubai'        => 4900,
        'istanbul'     => 2100,
        'cairo'        => 2300,
        'casablanca'   => 1700,
        'marrakech'    => 1600,
        'algiers'      => 900,
        'tripoli'      => 650,
        'malta'        => 290,
        'naples'       => 1100,
        'athens'       => 1600,
        'new york'     => 7900,
        'miami'        => 9000,
        'toronto'      => 8400,
        'tokyo'        => 9600,
        'bangkok'      => 8200,
        'singapore'    => 9000,
        'sydney'       => 16500,
        'johannesburg' => 7200,
        'nairobi'      => 4900,
        'dakar'        => 3500,
        'beirut'       => 2400,
        'amman'        => 2600,
        'riyadh'       => 4000,
        'moscow'       => 3300,
        'bali'         => 10500,
        'maldives'     => 7100,
        'cancun'       => 9800,
    ];

    // kg CO2 per km per passenger (average flight)
    private const KG_CO2_PER_KM = 0.255;

    public function calculate(string $destination, int $numberOfPeople): array
    {
        $distanceKm = $this->getDistance($destination);
        $co2PerPerson = round($distanceKm * self::KG_CO2_PER_KM);
        $totalCo2 = $co2PerPerson * $numberOfPeople;

        [$badge, $color, $label] = $this->getBadge($co2PerPerson);

        return [
            'distance_km'    => $distanceKm,
            'co2_per_person' => $co2PerPerson,
            'total_co2'      => $totalCo2,
            'badge'          => $badge,
            'color'          => $color,
            'label'          => $label,
        ];
    }

    /** Returns distance from destination string (case-insensitive keyword match). */
    public function getDistance(string $destination): int
    {
        $dest = strtolower($destination);
        foreach (self::DISTANCES as $key => $km) {
            if (str_contains($dest, $key)) {
                return $km;
            }
        }
        return 2500; // default for unknown destinations
    }

    private function getBadge(float $co2PerPerson): array
    {
        if ($co2PerPerson <= 400) {
            return ['🌿', '#22c55e', 'Eco-friendly'];
        }
        if ($co2PerPerson <= 900) {
            return ['🍂', '#f59e0b', 'Moderate'];
        }
        return ['🔥', '#ef4444', 'High impact'];
    }
}
