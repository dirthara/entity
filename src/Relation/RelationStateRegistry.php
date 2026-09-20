<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use WeakMap;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Exception\RelationLoadingException;

final class RelationStateRegistry
{
    /**
     * @var WeakMap<object, array<string, RelationState>>
     */
    private WeakMap $states;

    public function __construct()
    {
        $this->states = new WeakMap();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws RelationLoadingException
     */
    public function capture(EntityMetadata $metadata, object $entity, array $row): void
    {
        foreach ($metadata->relations as $relation) {
            $state = $this->state(entity: $entity, relation: $relation->property);

            if (!$relation instanceof BelongsToOneMetadata) {
                continue;
            }

            if (!array_key_exists($relation->foreignKey, $row)) {
                throw RelationLoadingException::missingForeignKeyColumn(
                    entity: $metadata->entity,
                    relation: $relation->property,
                    column: $relation->foreignKey,
                );
            }

            $state->captureForeignKey($row[$relation->foreignKey]);
        }
    }

    public function isLoaded(object $entity, string $relation): bool
    {
        return $this->state(entity: $entity, relation: $relation)->isLoaded();
    }

    public function markLoaded(object $entity, string $relation): void
    {
        $this->state(entity: $entity, relation: $relation)->markLoaded();
    }

    public function hasForeignKey(object $entity, string $relation): bool
    {
        return $this->state(entity: $entity, relation: $relation)->hasForeignKey();
    }

    public function foreignKey(object $entity, string $relation): mixed
    {
        return $this->state(entity: $entity, relation: $relation)->foreignKey();
    }

    private function state(object $entity, string $relation): RelationState
    {
        $relations = $this->states[$entity] ?? [];

        if (isset($relations[$relation])) {
            return $relations[$relation];
        }

        $state = new RelationState();

        $relations[$relation] = $state;
        $this->states[$entity] = $relations;

        return $state;
    }
}
