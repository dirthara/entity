<?php

declare(strict_types=1);

namespace Dirthara\Entity\Query;

use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;

final readonly class EntityQuery
{
    public function __construct(
        private EntityMetadata $entity,
        private QueryBuilder $queryBuilder,
    ) {}

    public function get(): Collection
    {
        return new ImmutableCollection();
    }
}
