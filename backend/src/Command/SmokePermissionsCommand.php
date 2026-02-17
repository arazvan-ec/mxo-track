<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\VisibilityScopeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:smoke:permissions', description: 'Smoke test de permisos por visibilidad de vehículos.')]
class SmokePermissionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VisibilityScopeService $visibilityScopeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('customer-email', null, InputOption::VALUE_REQUIRED, 'Email de customer para test puntual')
            ->addOption('vehicle-id', null, InputOption::VALUE_REQUIRED, 'Vehicle ID para test puntual');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerEmail = (string) $input->getOption('customer-email');
        $vehicleId = (string) $input->getOption('vehicle-id');

        if (($customerEmail === '') xor ($vehicleId === '')) {
            $output->writeln('smoke.permissions.error=missing_required_option_pair');
            $output->writeln('smoke.permissions.hint=use --customer-email and --vehicle-id together');

            return self::FAILURE;
        }

        if ($customerEmail !== '' && $vehicleId !== '') {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower($customerEmail)]);
            if (!$user instanceof User || !$user->hasRole('ROLE_CUSTOMER')) {
                $output->writeln('smoke.permissions.error=customer_not_found');
                return self::FAILURE;
            }

            $allowed = $this->visibilityScopeService->canAccessVehicle($user, $vehicleId);
            $output->writeln(sprintf('smoke.permissions.customer=%s', $customerEmail));
            $output->writeln(sprintf('smoke.permissions.vehicle=%s', $vehicleId));
            $output->writeln(sprintf('smoke.permissions.allowed=%s', $allowed ? '1' : '0'));

            return self::SUCCESS;
        }

        $users = $this->entityManager->getRepository(User::class)->findAll();
        $customers = 0;

        foreach ($users as $user) {
            if (!$user->hasRole('ROLE_CUSTOMER')) {
                continue;
            }
            $customers++;
            $ids = $this->visibilityScopeService->vehicleIdsFor($user);
            $output->writeln(sprintf('smoke.permissions.customer.%s.visible_vehicles=%d', $user->getEmail(), count($ids)));
        }

        $output->writeln(sprintf('smoke.permissions.customers_checked=%d', $customers));

        return self::SUCCESS;
    }
}
