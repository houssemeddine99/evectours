<?php

namespace App\Command;

use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:regenerate-slugs', description: 'Regenerate slugs for all voyages')]
class RegenerateSlugsCommand extends Command
{
    public function __construct(
        private readonly VoyageRepository $voyageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $voyages = $this->voyageRepository->findAll();

        foreach ($voyages as $voyage) {
            // Touch the title to force Gedmo Sluggable to regenerate the slug
            $voyage->setTitle($voyage->getTitle());
        }

        $this->entityManager->flush();
        $io->success(sprintf('Regenerated slugs for %d voyages.', count($voyages)));

        return Command::SUCCESS;
    }
}
