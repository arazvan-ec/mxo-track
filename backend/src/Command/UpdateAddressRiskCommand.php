<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AddressRiskService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:address-risk:update',
    description: 'Recalculate address risk scores from delivery history',
)]
class UpdateAddressRiskCommand extends Command
{
    public function __construct(
        private readonly AddressRiskService $addressRiskService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Address Risk Update');

        $count = $this->addressRiskService->updateRiskScores();

        $io->success(sprintf('Updated risk scores for %d addresses.', $count));

        return self::SUCCESS;
    }
}
