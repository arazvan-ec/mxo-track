<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AddressRiskRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AddressRiskRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_address_risk_address_hash', columns: ['address_hash'])]
class AddressRisk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $addressHash;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column]
    private int $totalDeliveries = 0;

    #[ORM\Column]
    private int $exceptionCount = 0;

    #[ORM\Column(type: 'float')]
    private float $exceptionRate = 0.0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $lastExceptionCodes = null;

    #[ORM\Column]
    private DateTimeImmutable $lastUpdated;

    public function __construct(string $addressHash, string $address)
    {
        $this->addressHash = $addressHash;
        $this->address = $address;
        $this->lastUpdated = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAddressHash(): string { return $this->addressHash; }
    public function getAddress(): string { return $this->address; }

    public function getTotalDeliveries(): int { return $this->totalDeliveries; }
    public function setTotalDeliveries(int $totalDeliveries): void { $this->totalDeliveries = $totalDeliveries; }

    public function getExceptionCount(): int { return $this->exceptionCount; }
    public function setExceptionCount(int $exceptionCount): void { $this->exceptionCount = $exceptionCount; }

    public function getExceptionRate(): float { return $this->exceptionRate; }
    public function setExceptionRate(float $exceptionRate): void { $this->exceptionRate = $exceptionRate; }

    public function getLastExceptionCodes(): ?array { return $this->lastExceptionCodes; }
    public function setLastExceptionCodes(?array $lastExceptionCodes): void { $this->lastExceptionCodes = $lastExceptionCodes; }

    public function getLastUpdated(): DateTimeImmutable { return $this->lastUpdated; }
    public function setLastUpdated(DateTimeImmutable $lastUpdated): void { $this->lastUpdated = $lastUpdated; }

    public function isHighRisk(): bool
    {
        return $this->exceptionRate > 0.3 && $this->totalDeliveries > 5;
    }
}
