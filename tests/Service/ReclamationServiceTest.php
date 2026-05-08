<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reclamation;
use App\Entity\Reservation;
use App\Repository\ReclamationRepository;
use App\Repository\ReservationRepository;
use App\Service\AuthService;
use App\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class ReclamationServiceTest extends TestCase
{
    private function makeService(
        ReclamationRepository $reclamationRepo,
        ReservationRepository $reservationRepo,
        EntityManagerInterface $em,
    ): ReclamationService {
        $auth = $this->createMock(AuthService::class);
        return new ReclamationService($reclamationRepo, $reservationRepo, $em, $auth);
    }

    /** Plain EM stub — only persist/flush, no QueryBuilder (for methods that don't call evaluateRefundEligibility). */
    private function makeSimpleEm(): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        return $em;
    }

    /** EM stub with a QueryBuilder that returns $pendingCount from getSingleScalarResult. */
    private function makeEm(int $pendingCount = 0): EntityManagerInterface
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSingleScalarResult'])
            ->getMock();
        $query->method('getSingleScalarResult')->willReturn($pendingCount);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        return $em;
    }

    // ── slaHours static helper ───────────────────────────────────────────────

    public function testSlaHoursUrgent(): void
    {
        $this->assertSame(4, ReclamationService::slaHours('URGENT'));
    }

    public function testSlaHoursHigh(): void
    {
        $this->assertSame(24, ReclamationService::slaHours('HIGH'));
    }

    public function testSlaHoursMedium(): void
    {
        $this->assertSame(72, ReclamationService::slaHours('MEDIUM'));
    }

    public function testSlaHoursLow(): void
    {
        $this->assertSame(120, ReclamationService::slaHours('LOW'));
    }

    public function testSlaHoursCaseInsensitive(): void
    {
        $this->assertSame(4, ReclamationService::slaHours('urgent'));
        $this->assertSame(24, ReclamationService::slaHours('high'));
    }

    // ── createReclamation ────────────────────────────────────────────────────

    public function testCreateReclamationPersistsAndReturnsEntity(): void
    {
        $repo    = $this->createMock(ReclamationRepository::class);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->makeService($repo, $resRepo, $em);

        $reclamation = $service->createReclamation([
            'reservation_id' => 5,
            'user_id'        => 10,
            'title'          => 'Missing luggage',
            'description'    => 'My bag did not arrive.',
            'priority'       => 'HIGH',
        ]);

        $this->assertInstanceOf(Reclamation::class, $reclamation);
        $this->assertSame(5, $reclamation->getReservationId());
        $this->assertSame(10, $reclamation->getUserId());
        $this->assertSame('Missing luggage', $reclamation->getTitle());
        $this->assertSame('OPEN', $reclamation->getStatus());
        $this->assertSame('HIGH', $reclamation->getPriority());
        $this->assertNotNull($reclamation->getReclamationDate());
        $this->assertNotNull($reclamation->getCreatedAt());
        $this->assertNotNull($reclamation->getResponseDeadline());
    }

    public function testCreateReclamationDefaultsToMediumPriority(): void
    {
        $repo    = $this->createMock(ReclamationRepository::class);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service     = $this->makeService($repo, $resRepo, $em);
        $reclamation = $service->createReclamation([
            'reservation_id' => 1,
            'user_id'        => 1,
            'title'          => 'Test',
            'description'    => 'Desc',
        ]);

        $this->assertSame('MEDIUM', $reclamation->getPriority());
    }

    public function testCreateReclamationDeadlineReflectsPriority(): void
    {
        $repo    = $this->createMock(ReclamationRepository::class);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service     = $this->makeService($repo, $resRepo, $em);
        $before      = new \DateTime();
        $reclamation = $service->createReclamation([
            'reservation_id' => 1,
            'user_id'        => 1,
            'title'          => 'T',
            'description'    => 'D',
            'priority'       => 'URGENT',
        ]);

        $deadline = $reclamation->getResponseDeadline();
        $this->assertNotNull($deadline);
        // Deadline should be ~4 hours from now (allow ±5s for test execution time)
        $diffSeconds = $deadline->getTimestamp() - $before->getTimestamp();
        $this->assertGreaterThanOrEqual(4 * 3600 - 5, $diffSeconds);
        $this->assertLessThanOrEqual(4 * 3600 + 5, $diffSeconds);
    }

    // ── updateStatus ─────────────────────────────────────────────────────────

    public function testUpdateStatusReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn(null);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $this->assertNull($service->updateStatus(999, 'CLOSED'));
    }

    public function testUpdateStatusChangesStatus(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setTitle('T')->setDescription('D')->setStatus('OPEN');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->updateStatus(1, 'RESOLVED');

        $this->assertSame('RESOLVED', $result->getStatus());
        $this->assertNotNull($result->getResolutionDate());
    }

    public function testUpdateStatusClosedSetsResolutionDate(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setTitle('T')->setDescription('D')->setStatus('OPEN');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->updateStatus(1, 'CLOSED');

        $this->assertNotNull($result->getResolutionDate());
    }

    public function testUpdateStatusOpenDoesNotSetResolutionDate(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setTitle('T')->setDescription('D')->setStatus('OPEN');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->updateStatus(1, 'IN_PROGRESS');

        $this->assertNull($result->getResolutionDate());
    }

    // ── addResponse ──────────────────────────────────────────────────────────

    public function testAddResponseReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn(null);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $this->assertNull($service->addResponse(999, 'Sorry for the issue.'));
    }

    public function testAddResponseSetsFieldsCorrectly(): void
    {
        $reclamation = new Reclamation();
        $reclamation->setTitle('T')->setDescription('D');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->addResponse(1, 'We apologise for the inconvenience.');

        $this->assertSame('We apologise for the inconvenience.', $result->getAdminResponse());
        $this->assertNotNull($result->getResponseDate());
    }

    // ── evaluateRefundEligibility ────────────────────────────────────────────

    public function testEligibilityFailsWhenReclamationNotFound(): void
    {
        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn(null);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertFalse($result['eligible']);
    }

    public function testEligibilityFailsWhenUserMismatch(): void
    {
        $reclamation = (new Reclamation())->setUserId(99)->setReservationId(1);

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $em      = $this->makeSimpleEm();

        $service = $this->makeService($repo, $resRepo, $em);
        $result  = $service->evaluateRefundEligibility(1, 1); // userId=1, reclamation has 99

        $this->assertFalse($result['eligible']);
    }

    public function testEligibilityFailsWhenReservationNotPaid(): void
    {
        $reclamation = (new Reclamation())->setUserId(1)->setReservationId(5);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUserId')->willReturn(1);
        $reservation->method('getStatus')->willReturn('CONFIRMED');
        $reservation->method('getPaymentStatus')->willReturn('PENDING');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);

        $service = $this->makeService($repo, $resRepo, $this->makeEm());
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertFalse($result['eligible']);
    }

    public function testEligibilitySucceedsForPaidConfirmedReservation(): void
    {
        $reclamation = (new Reclamation())->setUserId(1)->setReservationId(5);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUserId')->willReturn(1);
        $reservation->method('getStatus')->willReturn('CONFIRMED');
        $reservation->method('getPaymentStatus')->willReturn('PAID');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);

        $service = $this->makeService($repo, $resRepo, $this->makeEm(0));
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertTrue($result['eligible']);
    }

    public function testEligibilityFailsWhenPendingRefundExists(): void
    {
        $reclamation = (new Reclamation())->setUserId(1)->setReservationId(5);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getUserId')->willReturn(1);
        $reservation->method('getStatus')->willReturn('CONFIRMED');
        $reservation->method('getPaymentStatus')->willReturn('PAID');

        $repo = $this->createMock(ReclamationRepository::class);
        $repo->method('find')->willReturn($reclamation);
        $resRepo = $this->createMock(ReservationRepository::class);
        $resRepo->method('find')->willReturn($reservation);

        $service = $this->makeService($repo, $resRepo, $this->makeEm(1)); // 1 pending refund
        $result  = $service->evaluateRefundEligibility(1, 1);

        $this->assertFalse($result['eligible']);
    }
}
