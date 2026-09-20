<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\RelationLoadingException;

interface RelationLoader
{
    /**
     * @param list<string> $relations
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function assertLoadable(EntityMetadata $metadata, array $relations): void;

    /**
     * @param list<object> $entities
     * @param list<string> $relations
     * @param list<string> $without
     *
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     * @throws MappingException
     */
    public function load(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        array $relations,
        array $without = [],
    ): void;
}
