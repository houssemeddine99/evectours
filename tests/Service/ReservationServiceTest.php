<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use App\Service\LoyaltyPointsService;
use App\Service\RefundRequestService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ReservationServiceTest extends TestCase
{
    private function makeReservation(
        int $id,
        int $userId,
        int $voyageId,
        string $status = 'PENDING',
        string $paymentStatus = 'PENDING',
        string $totalPrice = '500.00',
    ): Reservation {
        $r = new Reservation();
        $r->setUserId($userId);
        $r->setVoyageId($voyageId);
        $r->setNumberOfPeople(2);
        $r->setTotalPrice($totalPrice);
        $r->setStatus($status);
        $r->setPaymentStatus($paymentStatus);
        $r->setReservationDate(new \DateTime());
        $r->setUpdatedAt(new \DateTime());

        $ref = new \ReflectionProperty(Reservation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($r, $id);

        return $r;
    }

    private function makeVoyage(int $id, string $title = 'Test Voyage'): Voyage
    {
        $v = new Voyage();
        $v->setTitle($title)->setDestination('Tunis')->setPrice('500.00');

        $ref = new \ReflectionProperty(Voyage::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($v, $id);

        return $v;
    }

    private function makeService(
        ReservationRepository $resRepo,
        VoyageRepository $voyageRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em,
    ): ReservationService {
        $refundService = $this->createMock(RefundRequestService::class);
        $loyalty       = $this->createMock(LoyaltyPointsService::class);
        return new ReservationService($resRepo, $voyageRepo, $userRepo, $refundService, $loyalty, $em, new NullLogger());
    }

    // ── cancelReservation ────────────────────────────────────────────────────

    public function testCancelReservationReturnsFalseWhenNotFound(): void
    {
        $resRepo   = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn(null);
        $voyageRepo = $this->createMock(VoyageRepository::class);
        $userRepo   = $this->createMock(UserRepository::class);
        $em         = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeService($resRepo, $voyageRepo, $userRepo, $em);
        $this->assertFalse($service->cancelReservation(999, 1));
    }

    public function testCancelReservationReturnsFalseWhenUserMismatch(): void
    {
        $reservation = $this->makeReservation(1, 10, 1, 'PENDING');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertFalse($service->cancelReservation(1, 99)); // userId 99 ≠ 10
    }

    public function testCancelReservationReturnsFalseForAlreadyCancelled(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'CANCELLED');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertFalse($service->cancelReservation(1, 1));
    }

    public function testCancelReservationSetsStatusAndFlushes(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'PENDING');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $result  = $service->cancelReservation(1, 1);

        $this->assertTrue($result);
        $this->assertSame('CANCELLED', $reservation->getStatus());
    }

    public function testCancelConfirmedReservationAlsoSucceeds(): void
    {
        $reservation = $this->makeReservation(2, 5, 3, 'CONFIRMED');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertTrue($service->cancelReservation(2, 5));
        $this->assertSame('CANCELLED', $reservation->getStatus());
    }

    // ── confirmReservationAsAdmin ─────────────────────────────────────────────

    public function testConfirmAsAdminReturnsFalseWhenNotFound(): void
    {
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertFalse($service->confirmReservationAsAdmin(999));
    }

    public function testConfirmAsAdminReturnsFalseForNonPending(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'CONFIRMED');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertFalse($service->confirmReservationAsAdmin(1));
    }

    public function testConfirmAsAdminSetsStatusAndPaymentFields(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'PENDING', 'PENDING', '750.00');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $loyalty = $this->createMock(LoyaltyPointsService::class);
        $loyalty->expects($this->once())->method('awardPoints')->with(1, 750.0);

        $service = new ReservationService(
            $resRepo,
            $this->createMock(VoyageRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(RefundRequestService::class),
            $loyalty,
            $em,
            new NullLogger()
        );

        $result = $service->confirmReservationAsAdmin(1, 'REF-001');

        $this->assertTrue($result);
        $this->assertSame('CONFIRMED', $reservation->getStatus());
        $this->assertSame('PAID', $reservation->getPaymentStatus());
        $this->assertSame('REF-001', $reservation->getPaymentReference());
        $this->assertNotNull($reservation->getPaymentDate());
    }

    // ── cancelReservationAsAdmin ──────────────────────────────────────────────

    public function testCancelAsAdminReturnsFalseWhenNotFound(): void
    {
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $this->assertFalse($service->cancelReservationAsAdmin(999));
    }

    public function testCancelAsAdminSetsStatusCancelled(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'CONFIRMED');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $em);
        $result  = $service->cancelReservationAsAdmin(1);

        $this->assertTrue($result);
        $this->assertSame('CANCELLED', $reservation->getStatus());
    }

    // ── createReservation ────────────────────────────────────────────────────

    public function testCreateReservationReturnsNullForInvalidInput(): void
    {
        $resRepo    = $this->createMock(ReservationRepository::class);
        $voyageRepo = $this->createMock(VoyageRepository::class);
        $userRepo   = $this->createMock(UserRepository::class);
        $em         = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $voyageRepo, $userRepo, $em);

        $this->assertNull($service->createReservation(1, 1, null, 0, 500.0)); // numberOfPeople = 0
        $this->assertNull($service->createReservation(1, 1, null, 2, -10.0)); // totalPrice < 0
    }

    public function testCreateReservationReturnsNullWhenUserNotFound(): void
    {
        $resRepo    = $this->createMock(ReservationRepository::class);
        $voyageRepo = $this->createMock(VoyageRepository::class);
        $userRepo   = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $voyageRepo, $userRepo, $em);
        $this->assertNull($service->createReservation(1, 1, null, 2, 500.0));
    }

    public function testCreateReservationReturnsNullWhenVoyageNotFound(): void
    {
        $user = $this->createMock(User::class);

        $resRepo    = $this->createMock(ReservationRepository::class);
        $voyageRepo = $this->createMock(VoyageRepository::class);
        $voyageRepo->method('find')->willReturn(null);
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn($user);
        $em = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $voyageRepo, $userRepo, $em);
        $this->assertNull($service->createReservation(1, 99, null, 2, 500.0));
    }

    // ── getReservationsForUser (N+1 fix) ─────────────────────────────────────

    public function testGetReservationsForUserReturnsMappedArray(): void
    {
        $r1 = $this->makeReservation(1, 5, 10, 'CONFIRMED', 'PAID', '800.00');
        $r2 = $this->makeReservation(2, 5, 11, 'PENDING', 'PENDING', '600.00');
        $v1 = $this->makeVoyage(10, 'Rome Trip');
        $v2 = $this->makeVoyage(11, 'Paris Trip');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('findByUserId')->with(5)->willReturn([$r1, $r2]);

        $voyageRepo = $this->createMock(VoyageRepository::class);
        // findByIds must be called ONCE with both voyage IDs (batch, not N+1)
        $voyageRepo->expects($this->once())
            ->method('findByIds')
            ->willReturn([$v1, $v2]);

        $userRepo = $this->createMock(UserRepository::class);
        $em       = $this->createMock(EntityManagerInterface::class);

        $service = $this->makeService($resRepo, $voyageRepo, $userRepo, $em);
        $result  = $service->getReservationsForUser(5);

        $this->assertCount(2, $result);
        $this->assertSame('Rome Trip', $result[0]['voyage_title']);
        $this->assertSame('Paris Trip', $result[1]['voyage_title']);
        $this->assertSame('CONFIRMED', $result[0]['status']);
    }

    public function testGetReservationsForUserReturnsEmptyArrayForNoReservations(): void
    {
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('findByUserId')->willReturn([]);

        $voyageRepo = $this->createMock(VoyageRepository::class);
        $voyageRepo->expects($this->never())->method('findByIds');

        $service = $this->makeService($resRepo, $voyageRepo, $this->createMock(UserRepository::class), $this->createMock(EntityManagerInterface::class));
        $this->assertSame([], $service->getReservationsForUser(1));
    }

    public function testGetReservationsForUserBatchesVoyageLookup(): void
    {
        $reservations = [
            $this->makeReservation(1, 1, 100),
            $this->makeReservation(2, 1, 101),
            $this->makeReservation(3, 1, 102),
        ];

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('findByUserId')->willReturn($reservations);

        $voyageRepo = $this->createMock(VoyageRepository::class);
        // MUST be called exactly once — not 3 times
        $voyageRepo->expects($this->once())
            ->method('findByIds')
            ->with($this->containsIdentical(100))
            ->willReturn([
                $this->makeVoyage(100, 'V1'),
                $this->makeVoyage(101, 'V2'),
                $this->makeVoyage(102, 'V3'),
            ]);

        $service = $this->makeService($resRepo, $voyageRepo, $this->createMock(UserRepository::class), $this->createMock(EntityManagerInterface::class));
        $result  = $service->getReservationsForUser(1);

        $this->assertCount(3, $result);
    }

    // ── evaluateRefundEligibility ─────────────────────────────────────────────

    public function testRefundEligibilityReturnsFalseWhenReservationNotFound(): void
    {
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn(null);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $this->createMock(EntityManagerInterface::class));
        $result  = $service->evaluateRefundEligibility(999, 1);

        $this->assertFalse($result['eligible']);
    }

    public function testRefundEligibilityReturnsFalseForAlreadyRefunded(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'CONFIRMED', 'REFUNDED');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $this->createMock(EntityManagerInterface::class));
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertFalse($result['eligible']);
    }

    public function testRefundEligibilityReturnsFalseWhenNotPaid(): void
    {
        $reservation = $this->makeReservation(1, 1, 1, 'CONFIRMED', 'PENDING');

        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);

        $service = $this->makeService($resRepo, $this->createMock(VoyageRepository::class), $this->createMock(UserRepository::class), $this->createMock(EntityManagerInterface::class));
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertFalse($result['eligible']);
    }
}
