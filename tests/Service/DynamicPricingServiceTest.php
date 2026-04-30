<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ReservationRepository;
use App\Service\DynamicPricingService;
use PHPUnit\Framework\TestCase;

class DynamicPricingServiceTest extends TestCase
{
    private function make(int $booked): DynamicPricingService
    {
        $repo = $this->createMock(ReservationRepository::class);
        $repo->method('sumBookedPeopleByVoyageId')->willReturn($booked);
        return new DynamicPricingService($repo);
    }

    public function testNoDemandNoUrgencyKeepsBasePrice(): void
    {
        $service = $this->make(0);
        $result  = $service->calculate(1000.0, 1, new \DateTime('+90 days'));

        $this->assertSame(1000.0, $result['base_price']);
        $this->assertSame(1000.0, $result['price']);
        $this->assertSame('none', $result['scarcity_level']);
        $this->assertSame('', $result['scarcity_label']);
    }

    public function testLowDemandAdds8Percent(): void
    {
        $service = $this->make(10); // 6–14 booked → +8%
        $result  = $service->calculate(1000.0, 1, new \DateTime('+90 days'));

        $this->assertSame(1080.0, $result['price']);
        $this->assertSame('low', $result['scarcity_level']);
    }

    public function testMediumDemandAdds15Percent(): void
    {
        $service = $this->make(20); // 15–29 → +15%
        $result  = $service->calculate(1000.0, 1, new \DateTime('+90 days'));

        $this->assertSame(1150.0, $result['price']);
        $this->assertSame('medium', $result['scarcity_level']);
    }

    public function testHighDemandAdds25Percent(): void
    {
        $service = $this->make(35); // ≥30 → +25%
        $result  = $service->calculate(1000.0, 1, new \DateTime('+90 days'));

        $this->assertSame(1250.0, $result['price']);
        $this->assertSame('high', $result['scarcity_level']);
    }

    public function testUrgencyLastMinuteAdds20Percent(): void
    {
        $service = $this->make(0);
        $result  = $service->calculate(1000.0, 1, new \DateTime('+5 days')); // ≤7 days → +20%

        $this->assertSame(1200.0, $result['price']);
        $this->assertSame('urgent', $result['scarcity_level']);
    }

    public function testSurchargeCapAt50Percent(): void
    {
        $service = $this->make(35); // +25% demand
        $result  = $service->calculate(1000.0, 1, new \DateTime('+3 days')); // +20% urgency → would be 45%, under cap

        $this->assertSame(1450.0, $result['price']);
    }

    public function testNullDepartureDateNoTimeSurcharge(): void
    {
        $service = $this->make(0);
        $result  = $service->calculate(500.0, 1, null);

        $this->assertSame(500.0, $result['price']);
        $this->assertSame('none', $result['scarcity_level']);
    }

    public function testPastDepartureDateNoTimeSurcharge(): void
    {
        $service = $this->make(0);
        $result  = $service->calculate(500.0, 1, new \DateTime('-1 day'));

        $this->assertSame(500.0, $result['price']);
    }

    public function testResultContainsAllKeys(): void
    {
        $service = $this->make(5);
        $result  = $service->calculate(200.0, 1, new \DateTime('+30 days'));

        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('base_price', $result);
        $this->assertArrayHasKey('scarcity_label', $result);
        $this->assertArrayHasKey('scarcity_level', $result);
        $this->assertArrayHasKey('booked', $result);
        $this->assertSame(5, $result['booked']);
    }
}
