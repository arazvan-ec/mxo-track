<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Route\Model\RouteStop;
use App\Entity\Concerns\PublicIdTrait;
use App\Repository\DriverFeedbackRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DriverFeedbackRepository::class)]
#[ORM\Table(name: 'driver_feedback')]
#[ORM\Index(columns: ['driver_id'], name: 'idx_driver_feedback_driver')]
#[ORM\Index(columns: ['stop_id'], name: 'idx_driver_feedback_stop')]
#[ORM\UniqueConstraint(name: 'uniq_driver_feedback_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class DriverFeedback
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(name: 'stop_id', nullable: true, onDelete: 'SET NULL')]
    private ?RouteStop $stop;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'driver_id', nullable: false)]
    private User $driver;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $correctedLat;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $correctedLng;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accessNotes;

    #[ORM\Column(nullable: true)]
    private ?int $actualServiceTimeSeconds;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        User $driver,
        ?RouteStop $stop = null,
        ?float $correctedLat = null,
        ?float $correctedLng = null,
        ?string $accessNotes = null,
        ?int $actualServiceTimeSeconds = null,
        ?string $comment = null,
    ) {
        $this->driver = $driver;
        $this->stop = $stop;
        $this->correctedLat = $correctedLat;
        $this->correctedLng = $correctedLng;
        $this->accessNotes = $accessNotes;
        $this->actualServiceTimeSeconds = $actualServiceTimeSeconds;
        $this->comment = $comment;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getStop(): ?RouteStop { return $this->stop; }
    public function getDriver(): User { return $this->driver; }
    public function getCorrectedLat(): ?float { return $this->correctedLat; }
    public function getCorrectedLng(): ?float { return $this->correctedLng; }
    public function getAccessNotes(): ?string { return $this->accessNotes; }
    public function getActualServiceTimeSeconds(): ?int { return $this->actualServiceTimeSeconds; }
    public function getComment(): ?string { return $this->comment; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
