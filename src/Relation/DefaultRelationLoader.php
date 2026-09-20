<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use TypeError;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
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
    public function load(ConnectedDatabase $database, EntityMetadata $metadata, array $entities, array $relations): void
    {
        if ($entities === [] || $relations === []) {
            return;
        }

        foreach ($entities as $entity) {
            if (!$entity instanceof $metadata->entity) {
                throw RelationLoadingException::invalidEntity(expected: $metadata->entity, actual: $entity::class);
            }
        }

        foreach ($relations as $relationName) {
            $relation = $metadata->relation($relationName);

            $pending = array_values(array_filter(
                $entities,
                fn(object $entity): bool => !$this->states->isLoaded(entity: $entity, relation: $relationName),
            ));

            if ($pending === []) {
                continue;
            }

            try {
                $this->loadRelation(database: $database, metadata: $metadata, relation: $relation, entities: $pending);
            } catch (DatabaseException $exception) {
                throw EntityDatabaseException::fromDatabaseException(
                    exception: $exception,
                    entity: $metadata->entity,
                    operation: sprintf('load relation "%s"', $relationName),
                );
            }
        }
    }

    /**
     * @param list<object> $entities
     *
     * @throws RelationLoadingException
     */
    private function loadRelation(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        RelationMetadata $relation,
        array $entities,
    ): void {
        match (true) {
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
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function loadBelongsToOne(
        ConnectedDatabase $database,
        BelongsToOneMetadata $relation,
        array $entities,
    ): void {
        $targetMetadata = $this->metadata->for($relation->target);
        $targetIdentifier = $this->singleIdentifier($targetMetadata);

        /**
         * @var array<string, mixed> $foreignKeys
         */
        $foreignKeys = [];

        /**
         * @var array<string, list<object>> $entitiesByForeignKey
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

            $foreignKeys[$key] = $foreignKey;
            $entitiesByForeignKey[$key][] = $entity;
        }

        if ($foreignKeys === []) {
            return;
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

        foreach ($entitiesByForeignKey as $key => $owners) {
            if (!isset($related[$key])) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $foreignKeys[$key],
                );
            }

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $related[$key]);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }
    }

    /**
     * @param list<object> $entities
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
    ): void {
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
                    identifier: $foreignKey,
                );
            }

            $related[$key] = $this->hydrate(metadata: $targetMetadata, row: $row);
        }

        foreach ($entitiesByIdentifier as $key => $owners) {
            $value = $related[$key] ?? null;

            if ($value === null && !$relation->nullable) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $identifiers[$key],
                );
            }

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $value);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }
    }

    /**
     * @param list<object> $entities
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
    ): void {
        $targetMetadata = $this->metadata->for($relation->target);

        [$identifiers, $entitiesByIdentifier] = $this->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<string, list<object>> $related
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

        foreach ($entitiesByIdentifier as $key => $owners) {
            $collection = new ImmutableCollection($related[$key] ?? []);

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $collection);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }
    }

    /**
     * @param list<object> $entities
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
    ): void {
        $targetMetadata = $this->metadata->for($relation->target);
        $targetIdentifier = $this->singleIdentifier($targetMetadata);

        [$identifiers, $entitiesByIdentifier] = $this->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<string, list<string>> $relatedKeysByOwner
         */
        $relatedKeysByOwner = [];

        /**
         * @var array<string, mixed> $relatedIdentifiers
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
            $relatedIdentifiers[$relatedKey] = $relatedIdentifier;
        }

        /**
         * @var array<string, object> $related
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

        foreach ($entitiesByIdentifier as $ownerKey => $owners) {
            $items = [];

            foreach ($relatedKeysByOwner[$ownerKey] ?? [] as $relatedKey) {
                if (!isset($related[$relatedKey])) {
                    throw RelationLoadingException::relatedEntityNotFound(
                        entity: $relation->target,
                        relation: $relation->property,
                        identifier: $relatedIdentifiers[$relatedKey],
                    );
                }

                $items[] = $related[$relatedKey];
            }

            $collection = new ImmutableCollection($items);

            foreach ($owners as $entity) {
                $this->write(entity: $entity, relation: $relation, value: $collection);

                $this->states->markLoaded(entity: $entity, relation: $relation->property);
            }
        }
    }

    /**
     * @param list<object> $entities
     *
     * @return array{
     *     0: array<string, mixed>,
     *     1: array<string, list<object>>
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
    private function identifierValue(EntityMetadata $metadata, object $entity): mixed
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

        return $identifier->single()->toDatabase($value);
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
            $property->setRawValue(object: $entity, value: $value);
        } catch (TypeError $exception) {
            throw RelationLoadingException::assignmentFailed(
                entity: $entity::class,
                relation: $relation->property,
                previous: $exception,
            );
        }
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
            return $this->properties[$entity][$property] = $this->reflection($entity)->getProperty($property);
        } catch (ReflectionException $exception) {
            throw RelationLoadingException::unknownProperty(entity: $entity, property: $property, previous: $exception);
        }
    }

    /**
     * @param class-string $entity
     *
     * @return ReflectionClass<object>
     *
     * @throws RelationLoadingException
     */
    private function reflection(string $entity): ReflectionClass
    {
        if (isset($this->classes[$entity])) {
            return $this->classes[$entity];
        }

        try {
            return $this->classes[$entity] = new ReflectionClass($entity);
        } catch (ReflectionException $exception) {
            throw RelationLoadingException::reflectionFailed(entity: $entity, previous: $exception);
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
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        throw RelationLoadingException::invalidIdentifierValue(value: $value);
    }
}
