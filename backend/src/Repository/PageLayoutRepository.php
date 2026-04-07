<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\PageLayout;
use App\Enum\PageKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PageLayout>
 */
final class PageLayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageLayout::class);
    }

    public function findOneByPublicId(string $publicId): ?PageLayout
    {
        try {
            return $this->findOneBy(['publicId' => Ulid::fromString($publicId)]);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Resolve the effective layout for a page: customer override → global fallback.
     */
    public function findForPage(PageKey $pageKey, ?Customer $customer = null): ?PageLayout
    {
        // Try customer-specific override first
        if ($customer !== null) {
            $override = $this->findOneBy([
                'pageKey' => $pageKey,
                'customer' => $customer,
                'active' => true,
            ]);
            if ($override !== null) {
                return $override;
            }
        }

        // Fall back to global layout (customer = null)
        return $this->findOneBy([
            'pageKey' => $pageKey,
            'customer' => null,
            'active' => true,
        ]);
    }

    /** @return PageLayout[] */
    public function findAllByPage(PageKey $pageKey): array
    {
        return $this->findBy(['pageKey' => $pageKey], ['createdAt' => 'ASC']);
    }
}
