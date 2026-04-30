<?php

namespace App\Command;

use App\Entity\Reclamation;
use App\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:escalate-reclamations',
    description: 'Escalates overdue reclamations to a higher priority and extends the SLA deadline.',
)]
class EscalateReclamationsCommand extends Command
{
    private const ESCALATION_MAP = [
        'LOW'    => 'NORMAL',
        'MEDIUM' => 'NORMAL',
        'NORMAL' => 'HIGH',
        'HIGH'   => 'URGENT',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
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

        if (empty($overdue)) {
            $io->success('No overdue reclamations found.');
            return Command::SUCCESS;
        }

        $escalated = 0;
        foreach ($overdue as $reclamation) {
            $currentPriority = strtoupper($reclamation->getPriority());

            if ($currentPriority === 'URGENT') {
                // Already at max — just extend deadline by 4h and log
                $newDeadline = (new \DateTime())->modify('+4 hours');
                $reclamation->setResponseDeadline($newDeadline);
                $reclamation->setUpdatedAt(new \DateTime());
                $this->logger->warning('Reclamation #{id} is URGENT and still overdue — deadline extended.', [
                    'id' => $reclamation->getId(),
                ]);
                $io->warning(sprintf('Reclamation #%d is URGENT and still overdue — deadline extended by 4h.', (int) $reclamation->getId()));
                $escalated++;
                continue;
            }

            $newPriority = self::ESCALATION_MAP[$currentPriority] ?? 'HIGH';
            $newHours    = ReclamationService::slaHours($newPriority);
            $newDeadline = (new \DateTime())->modify("+{$newHours} hours");

            $reclamation->setPriority($newPriority);
            $reclamation->setResponseDeadline($newDeadline);
            $reclamation->setUpdatedAt(new \DateTime());

            $this->logger->info('Reclamation #{id} escalated from {from} to {to}.', [
                'id'   => $reclamation->getId(),
                'from' => $currentPriority,
                'to'   => $newPriority,
            ]);
            $io->text(sprintf('  #%d  %s → %s  (new deadline: %s)', (int) $reclamation->getId(), $currentPriority, $newPriority, $newDeadline->format('Y-m-d H:i')));
            $escalated++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d reclamation(s) processed.', $escalated));
        return Command::SUCCESS;
    }
}
