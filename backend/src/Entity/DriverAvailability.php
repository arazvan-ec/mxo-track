<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'driver_availability')]
#[ORM\UniqueConstraint(name: 'uniq_driver_availability_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_driver_availability_driver', columns: ['driver_id'])]
#[ORM\Index(name: 'idx_driver_availability_day', columns: ['day_of_week'])]
#[ORM\HasLifecycleCallbacks]
class DriverAvailability
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'driver_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $driver;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 6)]
    private int $dayOfWeek;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'El formato debe ser HH:MM.')]
    private string $startTime;

    #[ORM\Column(length: 5)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'El formato debe ser HH:MM.')]
    private string $endTime;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $driver, int $dayOfWeek, string $startTime, string $endTime)
    {
        self::validateTimeFormat($startTime);
        self::validateTimeFormat($endTime);
        self::validateTimeRange($startTime, $endTime);

        $this->driver = $driver;
        $this->dayOfWeek = $dayOfWeek;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getDriver(): User { return $this->driver; }
    public function setDriver(User $driver): void { $this->driver = $driver; }

    public function getDayOfWeek(): int { return $this->dayOfWeek; }
    public function setDayOfWeek(int $dayOfWeek): void { $this->dayOfWeek = $dayOfWeek; }

    public function getStartTime(): string { return $this->startTime; }

    public function setStartTime(string $startTime): void
    {
        self::validateTimeFormat($startTime);
        self::validateTimeRange($startTime, $this->endTime);
        $this->startTime = $startTime;
    }

    public function getEndTime(): string { return $this->endTime; }

    public function setEndTime(string $endTime): void
    {
        self::validateTimeFormat($endTime);
        self::validateTimeRange($this->startTime, $endTime);
        $this->endTime = $endTime;
    }

    private static function validateTimeFormat(string $time): void
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            throw new \InvalidArgumentException(sprintf('Time "%s" must follow HH:MM format.', $time));
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            throw new \InvalidArgumentException(sprintf('Time "%s" contains invalid hours or minutes.', $time));
        }
    }

    private static function validateTimeRange(string $startTime, string $endTime): void
    {
        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException(sprintf(
                'startTime "%s" must be before endTime "%s".',
                $startTime,
                $endTime,
            ));
        }
    }

    public function isAvailable(): bool { return $this->isAvailable; }
    public function setAvailable(bool $isAvailable): void { $this->isAvailable = $isAvailable; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
