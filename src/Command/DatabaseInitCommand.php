<?php

namespace App\Command;

use App\Utility\DatabaseInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db-init',
    description: 'Initializes the database schema one time.',
)]
class DatabaseInitCommand extends Command
{
    public function __construct(
        private DatabaseInitializer $databaseInitializer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $io->info('Checking and initializing database schema...');
            
            $this->databaseInitializer->ensureSchema();
            
            $io->success('Database schema is ready!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to initialize database: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}