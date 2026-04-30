<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\WaitlistEntry;
use App\Repository\ReservationRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\WaitlistService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WaitlistServiceTest extends TestCase
{
    private WaitlistEntryRepository $waitlistRepo;
    private ReservationRepository   $reservationRepo;
    private EntityManagerInterface  $em;
    private WaitlistService         $service;

    protected function setUp(): void
    {
        $this->waitlistRepo    = $this->createMock(WaitlistEntryRepository::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $logger                = $this->createMock(LoggerInterface::class);

        $this->service = new WaitlistService(
            $this->waitlistRepo,
            $this->reservationRepo,
            $this->em,
            $logger
        );
    }

    // ── isHighDemand ────────────────────────────────────────────────────────

    public function testIsHighDemandReturnsFalseWhenBelowThreshold(): void
    {
        $this->reservationRepo->method('findByVoyageId')
            ->willReturn($this->mockReservations(9, 'CONFIRMED'));

        $this->assertFalse($this->service->isHighDemand(1));
    }

    public function testIsHighDemandReturnsTrueAtThreshold(): void
    {
        $this->reservationRepo->method('findByVoyageId')
            ->willReturn($this->mockReservations(10, 'CONFIRMED'));

        $this->assertTrue($this->service->isHighDemand(1));
    }

    public function testIsHighDemandReturnsTrueAboveThreshold(): void
    {
        $this->reservationRepo->method('findByVoyageId')
            ->willReturn($this->mockReservations(15, 'PENDING'));

        $this->assertTrue($this->service->isHighDemand(1));
    }

    public function testIsHighDemandIgnoresCancelledReservations(): void
    {
        $all = array_merge(
            $this->mockReservations(5, 'CONFIRMED'),
            $this->mockReservations(10, 'CANCELLED')
        );
        $this->reservationRepo->method('findByVoyageId')->willReturn($all);

        // Only 5 active — below threshold
        $this->assertFalse($this->service->isHighDemand(1));
    }

    public function testIsHighDemandCountsMixedActiveStatuses(): void
    {
        $all = array_merge(
            $this->mockReservations(6, 'CONFIRMED'),
            $this->mockReservations(5, 'PENDING'),
            $this->mockReservations(3, 'CANCELLED')
        );
        $this->reservationRepo->method('findByVoyageId')->willReturn($all);

        // 6 + 5 = 11 active → high demand
        $this->assertTrue($this->service->isHighDemand(1));
    }

    // ── getActiveReservationCount ────────────────────────────────────────────

    public function testGetActiveReservationCountReturnsOnlyActiveStatuses(): void
    {
        $all = array_merge(
            $this->mockReservations(4, 'CONFIRMED'),
            $this->mockReservations(2, 'PENDING'),
            $this->mockReservations(5, 'CANCELLED')
        );
        $this->reservationRepo->method('findByVoyageId')->willReturn($all);

        $this->assertSame(6, $this->service->getActiveReservationCount(1));
    }

    // ── isOnWaitlist ─────────────────────────────────────────────────────────

    public function testIsOnWaitlistReturnsTrueWhenEntryExists(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')
            ->with(42, 7)
            ->willReturn(new WaitlistEntry());

        $this->assertTrue($this->service->isOnWaitlist(42, 7));
    }

    public function testIsOnWaitlistReturnsFalseWhenNoEntry(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')
            ->willReturn(null);

        $this->assertFalse($this->service->isOnWaitlist(42, 7));
    }

    // ── getPosition ──────────────────────────────────────────────────────────

    public function testGetPositionReturnsOneBasedIndex(): void
    {
        $entries = $this->buildEntries([10, 20, 30]);
        $this->waitlistRepo->method('findByVoyageId')->willReturn($entries);

        $this->assertSame(1, $this->service->getPosition(10, 1));
        $this->assertSame(2, $this->service->getPosition(20, 1));
        $this->assertSame(3, $this->service->getPosition(30, 1));
    }

    public function testGetPositionReturnsNullWhenNotOnWaitlist(): void
    {
        $this->waitlistRepo->method('findByVoyageId')
            ->willReturn($this->buildEntries([1, 2, 3]));

        $this->assertNull($this->service->getPosition(99, 1));
    }

    public function testGetPositionReturnsNullOnEmptyQueue(): void
    {
        $this->waitlistRepo->method('findByVoyageId')->willReturn([]);

        $this->assertNull($this->service->getPosition(1, 1));
    }

    // ── join ─────────────────────────────────────────────────────────────────

    public function testJoinAddsNewEntryAndReturnsTrue(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->assertTrue($this->service->join(5, 3));
    }

    public function testJoinReturnsTrueWithoutPersistingIfAlreadyOnWaitlist(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')
            ->willReturn(new WaitlistEntry());

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->assertTrue($this->service->join(5, 3));
    }

    public function testJoinReturnsFalseOnDatabaseError(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')->willReturn(null);
        $this->em->method('flush')->willThrowException(new \RuntimeException('DB error'));

        $this->assertFalse($this->service->join(5, 3));
    }

    // ── leave ────────────────────────────────────────────────────────────────

    public function testLeaveRemovesEntryAndReturnsTrue(): void
    {
        $entry = new WaitlistEntry();
        $this->waitlistRepo->method('findByUserAndVoyage')->willReturn($entry);

        $this->em->expects($this->once())->method('remove')->with($entry);
        $this->em->expects($this->once())->method('flush');

        $this->assertTrue($this->service->leave(5, 3));
    }

    public function testLeaveReturnsFalseWhenEntryDoesNotExist(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')->willReturn(null);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->assertFalse($this->service->leave(5, 3));
    }

    public function testLeaveReturnsFalseOnDatabaseError(): void
    {
        $this->waitlistRepo->method('findByUserAndVoyage')
            ->willReturn(new WaitlistEntry());
        $this->em->method('flush')->willThrowException(new \RuntimeException('DB error'));

        $this->assertFalse($this->service->leave(5, 3));
    }

    // ── getNextEntry ─────────────────────────────────────────────────────────

    public function testGetNextEntryReturnsFirstInQueue(): void
    {
        $first  = (new WaitlistEntry())->setUserId(1);
        $second = (new WaitlistEntry())->setUserId(2);
        $this->waitlistRepo->method('findByVoyageId')->willReturn([$first, $second]);

        $this->assertSame($first, $this->service->getNextEntry(1));
    }

    public function testGetNextEntryReturnsNullWhenQueueIsEmpty(): void
    {
        $this->waitlistRepo->method('findByVoyageId')->willReturn([]);

        $this->assertNull($this->service->getNextEntry(1));
    }

    // ── markNotified ─────────────────────────────────────────────────────────

    public function testMarkNotifiedSetsNotifiedFlagAndFlushes(): void
    {
        $entry = new WaitlistEntry();
        $this->assertFalse($entry->isNotified());

        $this->waitlistRepo->method('find')->with(7)->willReturn($entry);
        $this->em->expects($this->once())->method('flush');

        $this->service->markNotified(7);

        $this->assertTrue($entry->isNotified());
    }

    public function testMarkNotifiedDoesNothingWhenEntryNotFound(): void
    {
        $this->waitlistRepo->method('find')->willReturn(null);
        $this->em->expects($this->never())->method('flush');

        $this->service->markNotified(999);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return \App\Entity\Reservation[] */
    private function mockReservations(int $count, string $status): array
    {
        $list = [];
        for ($i = 0; $i < $count; $i++) {
            $r = $this->createMock(\App\Entity\Reservation::class);
            $r->method('getStatus')->willReturn($status);
            $list[] = $r;
        }
        return $list;
    }

    /** @param int[] $userIds */
    private function buildEntries(array $userIds): array
    {
        return array_map(
            fn(int $uid) => (new WaitlistEntry())->setUserId($uid),
            $userIds
        );
    }
}
