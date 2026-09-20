<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Relation\Handle\RelationHandle;
use Dirthara\Entity\Exception\TypeConversionException;

interface RelationHandleFactory
{
    /**
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function handle(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        object $entity,
        string $relation,
    ): RelationHandle;
}
