<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create the default admin user if it does not exist',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin email', 'admin@transporte.local')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Admin password', 'ChangeMe_123!');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email');
        $password = $input->getOption('password');

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existing) {
            $io->info("Admin user '{$email}' already exists. Skipping.");

            return Command::SUCCESS;
        }

        $admin = new User($email);
        $admin->assignRole(UserRole::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
        $admin->setActive(true);
        $admin->setName('Administrador');

        $this->em->persist($admin);
        $this->em->flush();

        $io->success("Admin user '{$email}' created.");

        return Command::SUCCESS;
    }
}
