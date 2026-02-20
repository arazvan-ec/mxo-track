<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:positions:downsample', description: 'Consolida histórico de posiciones por ventana de tiempo.')]
class PositionsDownsampleCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Downsample placeholder implementado para cron nocturno.');
        return self::SUCCESS;
    }
}
