<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WidgetDefinition;
use App\Enum\WidgetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<WidgetDefinition>
 */
final class WidgetDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WidgetDefinition::class);
    }

    public function findOneByPublicId(string $publicId): ?WidgetDefinition
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function findByType(WidgetType $type): ?WidgetDefinition
    {
        return $this->findOneBy(['type' => $type]);
    }

    /** @return WidgetDefinition[] */
    public function findAllActive(): array
    {
        return $this->findBy(['active' => true], ['label' => 'ASC']);
    }
}
