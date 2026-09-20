<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Relation\Read\RelatedRows;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Relation\Read\HasOneReader;
use Dirthara\Entity\Relation\Read\HasManyReader;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Relation\Read\BelongsToOneReader;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Relation\Read\BelongsToManyReader;
use Dirthara\Entity\Exception\RelationLoadingException;

final class DefaultRelationLoader implements RelationLoader
{
    private readonly RelatedRows $rows;

    private readonly BelongsToOneReader $belongsToOne;

    private readonly HasOneReader $hasOne;

    private readonly HasManyReader $hasMany;

    private readonly BelongsToManyReader $belongsToMany;

    public function __construct(
        private readonly MetadataRegistry $metadata,
        Hydrator $hydrator,
        private readonly RelationStateRegistry $states,
    ) {
        $this->rows = new RelatedRows(metadata: $metadata, hydrator: $hydrator, states: $states);
        $this->belongsToOne = new BelongsToOneReader($this->rows);
        $this->hasOne = new HasOneReader($this->rows);
        $this->hasMany = new HasManyReader($this->rows);
        $this->belongsToMany = new BelongsToManyReader($this->rows);
    }

    /**
     * @param list<object> $entities
     * @param list<string> $relations
     *
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     * @throws MappingException
     */
    /**
     * @param list<string> $relations
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function assertLoadable(EntityMetadata $metadata, array $relations): void
    {
        $this->assertTree(metadata: $metadata, tree: RelationTree::fromPaths($relations));
    }

    /**
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function assertTree(EntityMetadata $metadata, RelationTree $tree): void
    {
        foreach ($tree->relations() as $relationName) {
            $relation = $metadata->relation($relationName);

            $nested = $tree->nestedFor($relationName);

            if ($nested->isEmpty()) {
                continue;
            }

            $this->assertTree(metadata: $this->metadata->for($relation->target), tree: $nested);
        }
    }

    /**
     * @param list<string> $relations
     * @param list<string> $without
     *
     * @return list<string>
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function wouldLoad(EntityMetadata $metadata, array $relations, array $without = []): array
    {
        return $this->wanted($metadata, RelationTree::fromPaths($relations), RelationTree::fromPaths($without), []);
    }

    public function load(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        array $relations,
        array $without = [],
    ): void {
        if ($entities === []) {
            return;
        }

        foreach ($entities as $entity) {
            $actual = $entity::class;

            if (!$entity instanceof $metadata->entity) {
                throw RelationLoadingException::invalidEntity(expected: $metadata->entity, actual: $actual);
            }
        }

        $this->loadTree(
            database: $database,
            metadata: $metadata,
            entities: $entities,
            tree: RelationTree::fromPaths($relations),
            without: RelationTree::fromPaths($without),
            seen: [],
        );
    }

    private function step(string $entity, string $relation): string
    {
        return sprintf('%s::%s', $entity, $relation);
    }

    /**
     * @param array<string, true> $seen
     *
     * @return list<string>
     */
    private function wanted(EntityMetadata $metadata, RelationTree $tree, RelationTree $without, array $seen): array
    {
        $relations = [];

        foreach ($tree->relations() as $relationName) {
            $relations[$relationName] = true;
        }

        foreach ($metadata->relations as $relation) {
            if ($relation->loading !== RelationLoading::Eager) {
                continue;
            }

            if (isset($seen[$this->step($metadata->entity, $relation->property)])) {
                continue;
            }

            $relations[$relation->property] = true;
        }

        foreach (array_keys($relations) as $relationName) {
            if (!($without->has($relationName) && $without->nestedFor($relationName)->isEmpty())) {
                continue;
            }

            unset($relations[$relationName]);
        }

        return array_keys($relations);
    }

    /**
     * @param list<object> $entities
     * @param array<string, true> $seen
     *
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function loadTree(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        RelationTree $tree,
        RelationTree $without,
        array $seen,
    ): void {
        foreach ($this->wanted($metadata, $tree, $without, $seen) as $relationName) {
            $relation = $metadata->relation($relationName);
            $nested = $tree->nestedFor($relationName);

            $pending = [];
            $already = [];

            foreach ($entities as $entity) {
                if ($this->states->isLoaded(entity: $entity, relation: $relationName)) {
                    $already[] = $entity;

                    continue;
                }

                $pending[] = $entity;
            }

            $related = [];

            if ($pending !== []) {
                try {
                    $related = $this->loadRelation(
                        database: $database,
                        metadata: $metadata,
                        relation: $relation,
                        entities: $pending,
                    );
                } catch (DatabaseException $exception) {
                    throw EntityDatabaseException::fromDatabaseException(
                        exception: $exception,
                        entity: $metadata->entity,
                        operation: sprintf('load relation "%s"', $relationName),
                    );
                }
            }

            $nestedWithout = $without->nestedFor($relationName);
            $nestedSeen = [...$seen, $this->step($metadata->entity, $relationName) => true];
            $targetMetadata = $this->metadata->for($relation->target);

            if ($nested->isEmpty() && $this->wanted($targetMetadata, $nested, $nestedWithout, $nestedSeen) === []) {
                continue;
            }

            foreach ($already as $entity) {
                $related = [...$related, ...$this->relatedOf(relation: $relation, entity: $entity)];
            }

            $related = $this->rows->distinct($related);

            if ($related === []) {
                continue;
            }

            $this->loadTree(
                database: $database,
                metadata: $targetMetadata,
                entities: $related,
                tree: $nested,
                without: $nestedWithout,
                seen: $nestedSeen,
            );
        }
    }

    /**
     * @return list<object>
     *
     * @throws RelationLoadingException
     */
    private function relatedOf(RelationMetadata $relation, object $entity): array
    {
        $property = $this->rows->property(entity: $entity::class, property: $relation->property);

        $value = $property->isInitialized($entity) ? $property->getRawValue($entity) : null;

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return is_object($value) ? [$value] : [];
        }

        $related = [];

        foreach ($value as $item) {
            if (!is_object($item)) {
                throw RelationLoadingException::invalidRelatedValue(
                    entity: $entity::class,
                    relation: $relation->property,
                    actual: get_debug_type($item),
                );
            }

            $related[] = $item;
        }

        return $related;
    }

    /**
     * @param list<object> $entities
     *
     * @return list<object>
     *
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function loadRelation(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        RelationMetadata $relation,
        array $entities,
    ): array {
        return match (true) {
            $relation instanceof BelongsToOneMetadata => $this->belongsToOne->read(
                database: $database,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof HasOneMetadata => $this->hasOne->read(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof HasManyMetadata => $this->hasMany->read(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof BelongsToManyMetadata => $this->belongsToMany->read(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            default => throw RelationLoadingException::unsupportedRelation(relation: $relation::class),
        };
    }
}
