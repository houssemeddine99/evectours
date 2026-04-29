<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CarbonFootprintService;
use PHPUnit\Framework\TestCase;

class CarbonFootprintServiceTest extends TestCase
{
    private CarbonFootprintService $service;

    protected function setUp(): void
    {
        $this->service = new CarbonFootprintService();
    }

    public function testKnownDestinationReturnsCorrectDistance(): void
    {
        $this->assertSame(1820, $this->service->getDistance('Paris, France'));
        $this->assertSame(2100, $this->service->getDistance('London, UK'));
        $this->assertSame(1700, $this->service->getDistance('Rome, Italy'));
    }

    public function testCaseInsensitiveMatching(): void
    {
        $this->assertSame(1820, $this->service->getDistance('PARIS'));
        $this->assertSame(1820, $this->service->getDistance('paris'));
    }

    public function testUnknownDestinationReturnsDefault(): void
    {
        $this->assertSame(2500, $this->service->getDistance('Some Unknown Place'));
    }

    public function testCalculateReturnsAllKeys(): void
    {
        $result = $this->service->calculate('Paris', 2);

        $this->assertArrayHasKey('distance_km', $result);
        $this->assertArrayHasKey('co2_per_person', $result);
        $this->assertArrayHasKey('total_co2', $result);
        $this->assertArrayHasKey('badge', $result);
        $this->assertArrayHasKey('color', $result);
        $this->assertArrayHasKey('label', $result);
    }

    public function testTotalCo2ScalesWithPeople(): void
    {
        $one = $this->service->calculate('Paris', 1);
        $two = $this->service->calculate('Paris', 2);

        $this->assertSame($two['total_co2'], $one['total_co2'] * 2);
        $this->assertSame($two['co2_per_person'], $one['co2_per_person']);
    }

    public function testShortFlightIsEcoFriendly(): void
    {
        $result = $this->service->calculate('Malta', 1); // 290 km → ~74 kg CO2

        $this->assertSame('Eco-friendly', $result['label']);
        $this->assertSame('🌿', $result['badge']);
    }

    public function testLongHaulIsHighImpact(): void
    {
        $result = $this->service->calculate('Sydney', 1); // 16500 km → ~4208 kg CO2

        $this->assertSame('High impact', $result['label']);
        $this->assertSame('🔥', $result['badge']);
    }

    public function testMediumFlightIsModerate(): void
    {
        $result = $this->service->calculate('Paris', 1); // 1820 km → ~464 kg CO2

        $this->assertSame('Moderate', $result['label']);
    }
}
