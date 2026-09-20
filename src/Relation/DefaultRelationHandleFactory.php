<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Relation\Handle\HasOneHandle;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Relation\Handle\HasManyHandle;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Relation\Handle\RelationHandle;
use Dirthara\Entity\Relation\Handle\RelationIdentity;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Relation\Handle\BelongsToOneHandle;
use Dirthara\Entity\Relation\Handle\BelongsToManyHandle;

final class DefaultRelationHandleFactory implements RelationHandleFactory
{
    private RelationIdentity $identity;

    public function __construct(
        private readonly MetadataRegistry $metadata,
        private readonly RelationStateRegistry $states,
    ) {
        $this->identity = new RelationIdentity();
    }

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
    ): RelationHandle {
        $relationMetadata = $metadata->relation($relation);

        $target = $this->metadata->for($relationMetadata->target);

        return match (true) {
            $relationMetadata instanceof BelongsToOneMetadata => new BelongsToOneHandle(
                database: $database,
                metadata: $metadata,
                target: $target,
                relation: $relationMetadata,
                entity: $entity,
                states: $this->states,
                identity: $this->identity,
            ),
            $relationMetadata instanceof HasOneMetadata => new HasOneHandle(
                database: $database,
                metadata: $metadata,
                target: $target,
                relation: $relationMetadata,
                entity: $entity,
                states: $this->states,
                identity: $this->identity,
            ),
            $relationMetadata instanceof HasManyMetadata => new HasManyHandle(
                database: $database,
                metadata: $metadata,
                target: $target,
                relation: $relationMetadata,
                entity: $entity,
                states: $this->states,
                identity: $this->identity,
            ),
            $relationMetadata instanceof BelongsToManyMetadata => new BelongsToManyHandle(
                database: $database,
                metadata: $metadata,
                target: $target,
                relation: $relationMetadata,
                entity: $entity,
                states: $this->states,
                identity: $this->identity,
            ),
            default => throw PersistenceException::unsupportedRelation(
                entity: $metadata->entity,
                relation: $relation,
                kind: $relationMetadata::class,
            ),
        };
    }
}
