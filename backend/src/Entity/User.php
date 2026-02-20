<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user_account')]
#[ORM\UniqueConstraint(name: 'uniq_user_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use PublicIdTrait;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $email)
    {
        $now = new \DateTimeImmutable();
        $this->email = mb_strtolower($email);
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getUserIdentifier(): string { return $this->email; }
    public function getEmail(): string { return $this->email; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function setRoles(array $roles): void { $this->roles = $roles; }

    public function assignRole(UserRole $role): void
    {
        $this->roles = array_values(array_unique([...$this->roles, $role->value]));
    }

    public function hasRole(string $role): bool { return in_array($role, $this->getRoles(), true); }

    public function getPassword(): string { return $this->passwordHash; }
    public function setPassword(string $passwordHash): void { $this->passwordHash = $passwordHash; }

    public function eraseCredentials(): void {}

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): void { $this->customer = $customer; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $active): void { $this->isActive = $active; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
