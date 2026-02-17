<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_account')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $email)
    {
        $this->id = Uuid::v7();
        $this->email = mb_strtolower($email);
    }

    public function getId(): Uuid { return $this->id; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getEmail(): string { return $this->email; }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function setRoles(array $roles): void { $this->roles = $roles; }
    public function hasRole(string $role): bool { return in_array($role, $this->getRoles(), true); }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): void { $this->password = $password; }

    public function eraseCredentials(): void {}

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): void { $this->customer = $customer; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $active): void { $this->isActive = $active; }
}
