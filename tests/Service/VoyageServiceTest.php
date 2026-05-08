<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Voyage;
use App\Repository\VoyageImageRepository;
use App\Repository\VoyageRepository;
use App\Service\DynamicPricingService;
use App\Service\VoyageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class VoyageServiceTest extends TestCase
{
    private function makeVoyage(int $id, string $title, string $destination, string $price, string $slug = ''): Voyage
    {
        $voyage = new Voyage();
        $voyage->setTitle($title);
        $voyage->setDestination($destination);
        $voyage->setPrice($price);
        $voyage->setSlug($slug ?: 'voyage-' . $id);
        $voyage->setStartDate(new \DateTime('+60 days'));
        $voyage->setEndDate(new \DateTime('+70 days'));
        $voyage->setCreatedAt(new \DateTime());

        // Inject the private id via reflection
        $ref = new \ReflectionProperty(Voyage::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($voyage, $id);

        return $voyage;
    }

    private function makePricing(): DynamicPricingService
    {
        $pricing = $this->createMock(DynamicPricingService::class);
        $pricing->method('calculate')->willReturnCallback(fn (float $base) => [
            'price'          => $base,
            'base_price'     => $base,
            'scarcity_label' => '',
            'scarcity_level' => 'none',
            'booked'         => 0,
        ]);
        $pricing->method('calculateWithBooked')->willReturnCallback(fn (float $base) => [
            'price'          => $base,
            'base_price'     => $base,
            'scarcity_label' => '',
            'scarcity_level' => 'none',
            'booked'         => 0,
        ]);
        $pricing->method('preloadBookedCounts')->willReturn([]);
        return $pricing;
    }

    private function makeService(
        VoyageRepository $repo,
        VoyageImageRepository $imageRepo,
        ?DynamicPricingService $pricing = null,
    ): VoyageService {
        $em = $this->createMock(EntityManagerInterface::class);
        return new VoyageService($repo, $imageRepo, $em, $pricing ?? $this->makePricing());
    }

    // ── createVoyage ─────────────────────────────────────────────────────────

    public function testCreateVoyagePersistsAndReturnsEntity(): void
    {
        $repo      = $this->createMock(VoyageRepository::class);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $em        = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new VoyageService($repo, $imageRepo, $em, $this->makePricing());
        $voyage  = $service->createVoyage([
            'title'       => 'Paris Adventure',
            'destination' => 'Paris, France',
            'price'       => '1200.00',
            'start_date'  => '2027-06-01',
            'end_date'    => '2027-06-10',
        ]);

        $this->assertInstanceOf(Voyage::class, $voyage);
        $this->assertSame('Paris Adventure', $voyage->getTitle());
        $this->assertSame('Paris, France', $voyage->getDestination());
        $this->assertSame('1200.00', $voyage->getPrice());
        $this->assertNotNull($voyage->getCreatedAt());
    }

    public function testCreateVoyageSetsDefaultsForMissingFields(): void
    {
        $repo      = $this->createMock(VoyageRepository::class);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $em        = $this->createMock(EntityManagerInterface::class);

        $service = new VoyageService($repo, $imageRepo, $em, $this->makePricing());
        $voyage  = $service->createVoyage([]);

        $this->assertSame('', $voyage->getTitle());
        $this->assertSame('', $voyage->getDestination());
        $this->assertNull($voyage->getPrice());
        $this->assertNull($voyage->getStartDate());
    }

    // ── deleteVoyage ─────────────────────────────────────────────────────────

    public function testDeleteVoyageReturnsFalseWhenNotFound(): void
    {
        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn(null);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $em        = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');

        $service = new VoyageService($repo, $imageRepo, $em, $this->makePricing());
        $this->assertFalse($service->deleteVoyage(999));
    }

    public function testDeleteVoyageReturnsTrueAndCallsRemove(): void
    {
        $voyage = $this->makeVoyage(1, 'Test', 'Paris', '500.00');
        $repo   = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn($voyage);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $em        = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($voyage);
        $em->expects($this->once())->method('flush');

        $service = new VoyageService($repo, $imageRepo, $em, $this->makePricing());
        $this->assertTrue($service->deleteVoyage(1));
    }

    // ── updateVoyage ─────────────────────────────────────────────────────────

    public function testUpdateVoyageReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn(null);
        $service = $this->makeService($repo, $this->createMock(VoyageImageRepository::class));

        $this->assertNull($service->updateVoyage(999, ['title' => 'New']));
    }

    public function testUpdateVoyageAppliesChanges(): void
    {
        $voyage = $this->makeVoyage(1, 'Old Title', 'Old Dest', '100.00');
        $repo   = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn($voyage);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new VoyageService($repo, $this->createMock(VoyageImageRepository::class), $em, $this->makePricing());
        $result  = $service->updateVoyage(1, [
            'title'       => 'New Title',
            'destination' => 'Rome, Italy',
            'price'       => '999.99',
        ]);

        $this->assertSame('New Title', $result->getTitle());
        $this->assertSame('Rome, Italy', $result->getDestination());
        $this->assertSame('999.99', $result->getPrice());
    }

    // ── getFeaturedVoyages ────────────────────────────────────────────────────

    public function testGetFeaturedVoyagesReturnsMappedArray(): void
    {
        $v1 = $this->makeVoyage(1, 'Rome Trip', 'Rome', '800.00', 'rome-trip');
        $v2 = $this->makeVoyage(2, 'Paris Trip', 'Paris', '900.00', 'paris-trip');

        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findFeatured')->willReturn([$v1, $v2]);

        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findImagesByVoyageIds')->willReturn([]);

        $service = $this->makeService($repo, $imageRepo);
        $result  = $service->getFeaturedVoyages(2);

        $this->assertCount(2, $result);
        $this->assertSame('Rome Trip', $result[0]['title']);
        $this->assertSame('Paris Trip', $result[1]['title']);
        $this->assertSame('rome-trip', $result[0]['slug']);
        $this->assertArrayHasKey('price', $result[0]);
        $this->assertArrayHasKey('image_url', $result[0]);
    }

    public function testGetFeaturedVoyagesFallbackSlug(): void
    {
        $v = $this->makeVoyage(7, 'Mystery Trip', 'Unknown', '500.00', '');

        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findFeatured')->willReturn([$v]);

        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findImagesByVoyageIds')->willReturn([]);

        $service = $this->makeService($repo, $imageRepo);
        $result  = $service->getFeaturedVoyages(1);

        $this->assertSame('voyage-7', $result[0]['slug']);
    }

    public function testGetFeaturedVoyagesEmptyWhenNoVoyages(): void
    {
        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findFeatured')->willReturn([]);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findImagesByVoyageIds')->willReturn([]);

        $service = $this->makeService($repo, $imageRepo);
        $this->assertSame([], $service->getFeaturedVoyages(6));
    }

    // ── getVoyageById ─────────────────────────────────────────────────────────

    public function testGetVoyageByIdReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn(null);
        $imageRepo = $this->createMock(VoyageImageRepository::class);

        $service = $this->makeService($repo, $imageRepo);
        $this->assertNull($service->getVoyageById(42));
    }

    public function testGetVoyageByIdReturnsArrayWithActivities(): void
    {
        $voyage = $this->makeVoyage(3, 'Beach Escape', 'Tunis', '450.00', 'beach-escape');
        $repo   = $this->createMock(VoyageRepository::class);
        $repo->method('find')->willReturn($voyage);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findByVoyageId')->willReturn([]);

        $service = $this->makeService($repo, $imageRepo);
        $result  = $service->getVoyageById(3);

        $this->assertIsArray($result);
        $this->assertSame('Beach Escape', $result['title']);
        $this->assertSame('Tunis', $result['destination']);
        $this->assertArrayHasKey('activities', $result);
        $this->assertIsArray($result['activities']);
    }

    // ── getVoyagesByIds (batch) ───────────────────────────────────────────────

    public function testGetVoyagesByIdsReturnsEmptyForEmptyInput(): void
    {
        $repo      = $this->createMock(VoyageRepository::class);
        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $service   = $this->makeService($repo, $imageRepo);

        $this->assertSame([], $service->getVoyagesByIds([]));
    }

    public function testGetVoyagesByIdsReturnsMappedById(): void
    {
        $v1 = $this->makeVoyage(10, 'Trip A', 'Tokyo', '1200.00', 'trip-a');
        $v2 = $this->makeVoyage(11, 'Trip B', 'Seoul', '1100.00', 'trip-b');

        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findByIds')->willReturn([$v1, $v2]);

        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findImagesByVoyageIds')->willReturn([]);

        $service = $this->makeService($repo, $imageRepo);
        $result  = $service->getVoyagesByIds([10, 11]);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(11, $result);
        $this->assertSame('Trip A', $result[10]['title']);
        $this->assertSame('Trip B', $result[11]['title']);
    }

    // ── dynamic pricing integration ───────────────────────────────────────────

    public function testMapVoyageUsesPreloadedBookedCounts(): void
    {
        $voyage = $this->makeVoyage(5, 'Safari', 'Kenya', '3000.00', 'safari');

        $repo = $this->createMock(VoyageRepository::class);
        $repo->method('findFeatured')->willReturn([$voyage]);

        $imageRepo = $this->createMock(VoyageImageRepository::class);
        $imageRepo->method('findImagesByVoyageIds')->willReturn([]);

        $pricing = $this->createMock(DynamicPricingService::class);
        $pricing->method('preloadBookedCounts')->with([5])->willReturn([5 => 20]);
        $pricing->expects($this->once())
            ->method('calculateWithBooked')
            ->with(3000.0, 5, $this->anything(), [5 => 20])
            ->willReturn([
                'price'          => 3450.0,
                'base_price'     => 3000.0,
                'scarcity_label' => '🟠 Spots filling up fast',
                'scarcity_level' => 'medium',
                'booked'         => 20,
            ]);
        $pricing->expects($this->never())->method('calculate');

        $service = new VoyageService($repo, $imageRepo, $this->createMock(EntityManagerInterface::class), $pricing);
        $result  = $service->getFeaturedVoyages(1);

        $this->assertSame('medium', $result[0]['scarcity_level']);
        $this->assertSame(20, $result[0]['booked_people']);
    }
}
