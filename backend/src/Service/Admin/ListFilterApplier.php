<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Doctrine\ORM\QueryBuilder;

final class ListFilterApplier
{
    /**
     * Apply filter definitions to both data and count QueryBuilders.
     *
     * @param FilterDefinition[] $filters
     */
    public function apply(QueryBuilder $qb, QueryBuilder $countQb, array $filters): void
    {
        foreach ($filters as $filter) {
            if (!$filter->isActive()) {
                continue;
            }

            $this->applyFilter($qb, $filter, isCount: false);
            $this->applyFilter($countQb, $filter, isCount: true);
        }
    }

    private function applyFilter(QueryBuilder $qb, FilterDefinition $filter, bool $isCount): void
    {
        // Handle countQb-specific joins (Route's re-alias case)
        if ($isCount && $filter->countJoin !== null) {
            $qb->leftJoin($filter->countJoin, $filter->countJoinAlias);
        }

        $condition = match ($filter->type) {
            'boolean', 'enum', 'entity' => sprintf('%s = :%s', $filter->field, $filter->paramName),
            'like' => sprintf('LOWER(%s) LIKE :%s', $filter->field, $filter->paramName),
            'date_gte' => sprintf('%s >= :%s', $filter->field, $filter->paramName),
            'date_lte' => sprintf('%s <= :%s', $filter->field, $filter->paramName),
            default => null,
        };

        if ($condition === null) {
            return;
        }

        // For countQb with re-aliased joins, replace the original alias with the count alias
        if ($isCount && $filter->countJoinAlias !== null) {
            $originalAlias = explode('.', $filter->field)[0];
            $condition = str_replace($originalAlias . '.', $filter->countJoinAlias . '.', $condition);
        }

        $qb->andWhere($condition)->setParameter($filter->paramName, $filter->value);
    }
}
