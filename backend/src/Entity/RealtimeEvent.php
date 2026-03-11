<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Repository\RealtimeEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RealtimeEventRepository::class)]
#[ORM\Table(name: 'realtime_event')]
#[ORM\Index(name: 'idx_re_customer_topic_time', columns: ['customer_id', 'topic', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class RealtimeEvent implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 255)]
    private string $topic;

    #[ORM\Column(type: Types::JSON)]
    private array $data;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $eventType;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, string $topic, array $data, ?string $eventType = null)
    {
        $this->customer = $customer;
        $this->topic = $topic;
        $this->data = $data;
        $this->eventType = $eventType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
