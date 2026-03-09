<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'webhook_endpoint')]
#[ORM\UniqueConstraint(name: 'uniq_webhook_endpoint_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class WebhookEndpoint implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column(type: Types::JSON)]
    private array $events = [];

    #[ORM\Column(length: 128)]
    private string $secret;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, string $url, string $secret)
    {
        $this->customer = $customer;
        $this->url = $url;
        $this->secret = $secret;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /** @return string[] */
    public function getEvents(): array
    {
        return $this->events;
    }

    /** @param string[] $events */
    public function setEvents(array $events): void
    {
        $this->events = $events;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function supportsEvent(string $eventType): bool
    {
        if ($this->events === []) {
            return true; // empty = all events
        }

        return in_array($eventType, $this->events, true);
    }
}
