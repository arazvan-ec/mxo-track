<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Route\Projection\ProjectionRebuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:projection:rebuild',
    description: 'Rebuild route_current_state and stop_current_status projection tables from entity state',
)]
final class ProjectionRebuildCommand extends Command
{
    public function __construct(
        private readonly ProjectionRebuilder $rebuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rebuilding projection tables');

        $result = $this->rebuilder->rebuildAll();

        $io->success(sprintf(
            'Rebuilt projections: %d routes, %d stops.',
            $result['routes'],
            $result['stops'],
        ));

        return Command::SUCCESS;
    }
}
