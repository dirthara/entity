<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use TypeError;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use ReflectionNamedType;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final class DefaultRelationLoader implements RelationLoader
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $classes = [];

    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    public function __construct(
        private readonly MetadataRegistry $metadata,
        private readonly Hydrator $hydrator,
        private readonly RelationStateRegistry $states,
    ) {}

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

    public function load(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        array $relations,
        array $without = [],
    ): void {
        if ($entities === [] || $relations === []) {
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
            seen: [$metadata->entity => true],
        );
    }

    /**
     * @param array<class-string, true> $seen
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

            if (isset($seen[$relation->target])) {
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
     * @param array<class-string, true> $seen
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
            $nestedSeen = [...$seen, $relation->target => true];
            $targetMetadata = $this->metadata->for($relation->target);

            if ($nested->isEmpty() && $this->wanted($targetMetadata, $nested, $nestedWithout, $nestedSeen) === []) {
                continue;
            }

            foreach ($already as $entity) {
                $related = [...$related, ...$this->relatedOf(relation: $relation, entity: $entity)];
            }

            $related = $this->distinct($related);

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
     */
    private function relatedOf(RelationMetadata $relation, object $entity): array
    {
        $property = $this->property(entity: $entity::class, property: $relation->property);

        $value = $property->isInitialized($entity) ? $property->getRawValue($entity) : null;

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return is_object($value) ? [$value] : [];
        }

        $related = [];

        foreach ($value as $item) {
            assert(is_object($item), description: 'A loaded relation only ever holds entities');

            $related[] = $item;
        }

        return $related;
    }

    /**
     * @param list<object> $entities
     *
     * @return list<object>
     */
    private function distinct(array $entities): array
    {
        $unique = [];

        foreach ($entities as $entity) {
            $unique[spl_object_id($entity)] = $entity;
        }

        return array_values($unique);
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
            $relation instanceof BelongsToOneMetadata => $this->loadBelongsToOne(
                database: $database,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof HasOneMetadata => $this->loadHasOne(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof HasManyMetadata => $this->loadHasMany(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            $relation instanceof BelongsToManyMetadata => $this->loadBelongsToMany(
                database: $database,
                metadata: $metadata,
                relation: $relation,
                entities: $entities,
            ),
            default => throw RelationLoadingException::unsupportedRelation(relation: $relation::class),
        };
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
    private function loadBelongsToOne(
        ConnectedDatabase $database,
        BelongsToOneMetadata $relation,
        array $entities,
    ): array {
        $targetMetadata = $this->metadata->for($relation->target);
        $targetIdentifier = $this->singleIdentifier($targetMetadata);

        /**
         * @var array<array-key, string|int|float|bool> $foreignKeys
         */
        $foreignKeys = [];

        /**
         * @var array<array-key, list<object>> $entitiesByForeignKey
         */
        $entitiesByForeignKey = [];

        foreach ($entities as $entity) {
            if (!$this->states->hasForeignKey($entity, $relation->property)) {
                throw RelationLoadingException::foreignKeyNotCaptured(
                    entity: $entity::class,
                    relation: $relation->property,
                );
            }

            $foreignKey = $this->states->foreignKey(entity: $entity, relation: $relation->property);

            if ($foreignKey === null) {
                if (!$relation->nullable) {
                    throw RelationLoadingException::nullRelationNotAllowed(
                        entity: $entity::class,
                        relation: $relation->property,
                    );
                }

                $this->write(entity: $entity, relation: $relation, value: null);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);

                continue;
            }

            $key = $this->key($foreignKey);

            $foreignKeys[$key] = $this->scalar($foreignKey);
            $entitiesByForeignKey[$key][] = $entity;
        }

        if ($foreignKeys === []) {
            return [];
        }

        $related = [];

        foreach ($database
            ->table($targetMetadata->table)
            ->whereIn($targetIdentifier->column(), array_values($foreignKeys))
            ->get() as $row) {
            $identifier = $this->rowValue(
                row: $row,
                column: $targetIdentifier->column(),
                entity: $targetMetadata->entity,
                relation: $relation->property,
            );

            $related[$this->key($identifier)] = $this->hydrate(metadata: $targetMetadata, row: $row);
        }

        $loaded = [];

        foreach ($entitiesByForeignKey as $key => $owners) {
            if (!isset($related[$key])) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            $loaded[] = $related[$key];

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $related[$key]);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }

        return $loaded;
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
    private function loadHasOne(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        HasOneMetadata $relation,
        array $entities,
    ): array {
        $targetMetadata = $this->metadata->for($relation->target);

        [$identifiers, $entitiesByIdentifier] = $this->groupByIdentifier(metadata: $metadata, entities: $entities);

        $related = [];

        foreach ($database
            ->table($targetMetadata->table)
            ->whereIn($relation->foreignKey, array_values($identifiers))
            ->get() as $row) {
            $foreignKey = $this->rowValue(
                row: $row,
                column: $relation->foreignKey,
                entity: $targetMetadata->entity,
                relation: $relation->property,
            );

            $key = $this->key($foreignKey);

            if (isset($related[$key])) {
                throw RelationLoadingException::multipleRelatedEntities(
                    entity: $metadata->entity,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            $related[$key] = $this->hydrate(metadata: $targetMetadata, row: $row);
        }

        $loaded = [];

        foreach ($entitiesByIdentifier as $key => $owners) {
            $value = $related[$key] ?? null;

            if ($value === null && !$relation->nullable) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            if ($value !== null) {
                $loaded[] = $value;
            }

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $value);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }

        return $loaded;
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
    private function loadHasMany(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        HasManyMetadata $relation,
        array $entities,
    ): array {
        $targetMetadata = $this->metadata->for($relation->target);

        [$identifiers, $entitiesByIdentifier] = $this->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<array-key, list<object>> $related
         */
        $related = [];

        foreach ($database
            ->table($targetMetadata->table)
            ->whereIn($relation->foreignKey, array_values($identifiers))
            ->get() as $row) {
            $foreignKey = $this->rowValue(
                row: $row,
                column: $relation->foreignKey,
                entity: $targetMetadata->entity,
                relation: $relation->property,
            );

            $related[$this->key($foreignKey)][] = $this->hydrate(metadata: $targetMetadata, row: $row);
        }

        $loaded = [];

        foreach ($entitiesByIdentifier as $key => $owners) {
            $items = $related[$key] ?? [];

            $loaded = [...$loaded, ...$items];

            $collection = new ImmutableCollection($items);

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $collection);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }

        return $loaded;
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
    private function loadBelongsToMany(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        BelongsToManyMetadata $relation,
        array $entities,
    ): array {
        $targetMetadata = $this->metadata->for($relation->target);
        $targetIdentifier = $this->singleIdentifier($targetMetadata);

        [$identifiers, $entitiesByIdentifier] = $this->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<array-key, list<array-key>> $relatedKeysByOwner
         */
        $relatedKeysByOwner = [];

        /**
         * @var array<array-key, string|int|float|bool> $relatedIdentifiers
         */
        $relatedIdentifiers = [];

        foreach ($database
            ->table($relation->table)
            ->whereIn($relation->foreignKey, array_values($identifiers))
            ->get() as $row) {
            $ownerIdentifier = $this->rowValue(
                row: $row,
                column: $relation->foreignKey,
                entity: $metadata->entity,
                relation: $relation->property,
            );

            $relatedIdentifier = $this->rowValue(
                row: $row,
                column: $relation->relatedForeignKey,
                entity: $relation->target,
                relation: $relation->property,
            );

            $ownerKey = $this->key($ownerIdentifier);
            $relatedKey = $this->key($relatedIdentifier);

            $relatedKeysByOwner[$ownerKey][] = $relatedKey;
            $relatedIdentifiers[$relatedKey] = $this->scalar($relatedIdentifier);
        }

        /**
         * @var array<array-key, object> $related
         */
        $related = [];

        if ($relatedIdentifiers !== []) {
            foreach ($database
                ->table($targetMetadata->table)
                ->whereIn($targetIdentifier->column(), array_values($relatedIdentifiers))
                ->get() as $row) {
                $identifier = $this->rowValue(
                    row: $row,
                    column: $targetIdentifier->column(),
                    entity: $targetMetadata->entity,
                    relation: $relation->property,
                );

                $related[$this->key($identifier)] = $this->hydrate(metadata: $targetMetadata, row: $row);
            }
        }

        $loaded = [];

        foreach ($entitiesByIdentifier as $ownerKey => $owners) {
            $items = [];

            foreach ($relatedKeysByOwner[$ownerKey] ?? [] as $relatedKey) {
                if (!isset($related[$relatedKey])) {
                    throw RelationLoadingException::relatedEntityNotFound(
                        entity: $relation->target,
                        relation: $relation->property,
                        identifier: $relatedKey,
                    );
                }

                $items[] = $related[$relatedKey];
            }

            $loaded = [...$loaded, ...$items];

            $collection = new ImmutableCollection($items);

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $collection);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }

        return $this->distinct($loaded);
    }

    /**
     * @param list<object> $entities
     *
     * @return array{
     *     0: array<array-key, string|int|float|bool>,
     *     1: array<array-key, list<object>>
     * }
     */
    private function groupByIdentifier(EntityMetadata $metadata, array $entities): array
    {
        $identifiers = [];
        $entitiesByIdentifier = [];

        foreach ($entities as $entity) {
            $identifier = $this->identifierValue(metadata: $metadata, entity: $entity);

            $key = $this->key($identifier);

            $identifiers[$key] = $identifier;
            $entitiesByIdentifier[$key][] = $entity;
        }

        return [$identifiers, $entitiesByIdentifier];
    }

    /**
     * @throws MappingException
     * @throws RelationLoadingException
     */
    private function identifierValue(EntityMetadata $metadata, object $entity): string|int|float|bool
    {
        $identifier = $this->singleIdentifier($metadata);

        $reflection = $this->property(entity: $metadata->entity, property: $identifier->property);

        if (!$reflection->isInitialized($entity)) {
            throw RelationLoadingException::uninitializedIdentifier(
                entity: $metadata->entity,
                property: $identifier->property,
            );
        }

        $value = $reflection->getRawValue($entity);

        if ($value === null) {
            throw RelationLoadingException::nullIdentifier(entity: $metadata->entity, property: $identifier->property);
        }

        return $this->scalar($identifier->single()->toDatabase($value));
    }

    /**
     * @throws RelationLoadingException
     * @throws InvalidIdentifierException
     */
    private function singleIdentifier(EntityMetadata $metadata): PropertyMetadata
    {
        if (!$metadata->identifier->isSingle()) {
            throw RelationLoadingException::compositeIdentifier(entity: $metadata->entity);
        }

        $identifier = $metadata->identifier->single();

        if (!$identifier->isSingle()) {
            throw RelationLoadingException::compositeIdentifier(entity: $metadata->entity);
        }

        return $identifier;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws TypeConversionException
     * @throws CreateEntityException
     * @throws HydrationException
     * @throws RelationLoadingException
     */
    private function hydrate(EntityMetadata $metadata, array $row): object
    {
        $entity = $this->hydrator->newInstance($metadata);

        $this->hydrator->hydrate(metadata: $metadata, entity: $entity, data: $row);

        $this->states->capture(metadata: $metadata, entity: $entity, row: $row);

        return $entity;
    }

    /**
     * @throws RelationLoadingException
     */
    private function write(object $entity, RelationMetadata $relation, mixed $value): void
    {
        $property = $this->property(entity: $entity::class, property: $relation->property);

        try {
            $property->setRawValue(object: $entity, value: $this->writable(property: $property, value: $value));
        } catch (TypeError $exception) {
            throw RelationLoadingException::assignmentFailed(
                entity: $entity::class,
                relation: $relation->property,
                previous: $exception,
            );
        }
    }

    private function writable(ReflectionProperty $property, mixed $value): mixed
    {
        if (!$value instanceof Collection) {
            return $value;
        }

        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * @param class-string $entity
     *
     * @throws RelationLoadingException
     */
    private function property(string $entity, string $property): ReflectionProperty
    {
        if (isset($this->properties[$entity][$property])) {
            return $this->properties[$entity][$property];
        }

        try {
            $reflection = $this->classes[$entity] ??= new ReflectionClass($entity);

            return $this->properties[$entity][$property] = $reflection->getProperty($property);
        } catch (ReflectionException $exception) {
            throw RelationLoadingException::unknownProperty(entity: $entity, property: $property, previous: $exception);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws RelationLoadingException
     */
    private function rowValue(array $row, string $column, string $entity, string $relation): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw RelationLoadingException::missingForeignKeyColumn(
                entity: $entity,
                relation: $relation,
                column: $column,
            );
        }

        return $row[$column];
    }

    /**
     * @throws RelationLoadingException
     */
    private function key(mixed $value): string
    {
        return (string) $this->scalar($value);
    }

    /**
     * @throws RelationLoadingException
     */
    private function scalar(mixed $value): string|int|float|bool
    {
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw RelationLoadingException::invalidIdentifierValue(value: $value);
    }
}
