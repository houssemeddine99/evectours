<?php

namespace App\MessageHandler;

use App\Entity\Reclamation;
use App\Message\EscalateReclamationsMessage;
use App\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EscalateReclamationsHandler
{
    private const ESCALATION_MAP = [
        'LOW'    => 'MEDIUM',
        'MEDIUM' => 'HIGH',
        'HIGH'   => 'URGENT',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EscalateReclamationsMessage $message): void
    {
        $now = new \DateTime();

        /** @var Reclamation[] $overdue */
        $overdue = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Reclamation::class, 'r')
            ->where('r.responseDeadline IS NOT NULL')
            ->andWhere('r.responseDeadline < :now')
            ->andWhere('r.status NOT IN (:closed)')
            ->setParameter('now', $now)
            ->setParameter('closed', ['RESOLVED', 'CLOSED'])
            ->getQuery()
            ->getResult();

        foreach ($overdue as $reclamation) {
            $currentPriority = strtoupper($reclamation->getPriority());

            if ($currentPriority === 'URGENT') {
                $reclamation->setResponseDeadline((new \DateTime())->modify('+4 hours'));
                $reclamation->setUpdatedAt(new \DateTime());
                $this->logger->warning('Reclamation #{id} is URGENT and still overdue — deadline extended.', [
                    'id' => $reclamation->getId(),
                ]);
                continue;
            }

            $newPriority = self::ESCALATION_MAP[$currentPriority] ?? 'HIGH';
            $newDeadline = (new \DateTime())->modify('+' . ReclamationService::slaHours($newPriority) . ' hours');

            $reclamation->setPriority($newPriority);
            $reclamation->setResponseDeadline($newDeadline);
            $reclamation->setUpdatedAt(new \DateTime());

            $this->logger->info('Reclamation #{id} escalated {from} → {to}.', [
                'id'   => $reclamation->getId(),
                'from' => $currentPriority,
                'to'   => $newPriority,
            ]);
        }

        $this->entityManager->flush();
    }
}
