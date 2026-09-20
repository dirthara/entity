<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;

interface RelationLoader
{
    /**
     * @param list<object> $entities
     * @param list<string> $relations
     */
    public function load(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        array $relations,
    ): void;
}
