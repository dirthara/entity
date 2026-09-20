<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Exception\TypeConversionException;

final class ReflectionPersister implements EntityPersister
{
    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    public function __construct(
        private readonly MetadataRegistry $metadata,
    ) {}

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function insert(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): void
    {
        $this->assertEntity($metadata, $entity);

        $values = [];

        foreach ($metadata->properties as $property) {
            if ($property->generated) {
                continue;
            }

            $values[$property->column()] = $this->databaseValue(
                entity: $entity,
                metadata: $metadata,
                property: $property,
            );
        }

        $this->assertRelationsInitialized(metadata: $metadata, entity: $entity);

        $values = [...$values, ...$this->relationValues(metadata: $metadata, entity: $entity)];

        $generated = $this->generatedIdentifier($metadata);

        try {
            if ($generated === null) {
                $database->table($metadata->table)->insert($values);

                return;
            }

            $identifier = $database->table($metadata->table)->insertGetId($values, $generated->column());
        } catch (DatabaseException $exception) {
            throw PersistenceException::insertFailed(entity: $metadata->entity, previous: $exception);
        }

        if ($identifier === null) {
            throw PersistenceException::missingGeneratedIdentifier(
                entity: $metadata->entity,
                property: $generated->property,
            );
        }

        $this->property(entity: $metadata->entity, property: $generated->property)->setRawValue(
            $entity,
            $generated->single()->fromDatabase($identifier),
        );
    }

    private function generatedIdentifier(EntityMetadata $metadata): ?PropertyMetadata
    {
        if (!$metadata->identifier->isSingle()) {
            return null;
        }

        $property = $metadata->identifier->properties[0];

        return $property->generated ? $property : null;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function update(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int
    {
        $this->assertEntity($metadata, $entity);

        $values = [];

        foreach ($metadata->properties as $property) {
            if ($property->identifier || $property->generated) {
                continue;
            }

            $values[$property->column()] = $this->databaseValue(
                entity: $entity,
                metadata: $metadata,
                property: $property,
            );
        }

        $values = [...$values, ...$this->relationValues(metadata: $metadata, entity: $entity)];

        if ($values === []) {
            return 0;
        }

        $query = $database->table($metadata->table);

        foreach ($metadata->identifier->properties as $property) {
            $query->where(
                $property->column(),
                ComparisonOperator::Equal,
                $this->databaseValue(entity: $entity, metadata: $metadata, property: $property),
            );
        }

        try {
            $affected = $query->update($values);
        } catch (DatabaseException $exception) {
            throw PersistenceException::updateFailed(entity: $metadata->entity, previous: $exception);
        }

        if ($affected > 1) {
            throw PersistenceException::unexpectedAffectedRows(
                entity: $metadata->entity,
                operation: 'update',
                expectedMaximum: 1,
                actual: $affected,
            );
        }

        return $affected;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function delete(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int
    {
        $this->assertEntity($metadata, $entity);

        $query = $database->table($metadata->table);

        foreach ($metadata->identifier->properties as $property) {
            $query->where(
                $property->column(),
                ComparisonOperator::Equal,
                $this->databaseValue(entity: $entity, metadata: $metadata, property: $property),
            );
        }

        try {
            $affected = $query->delete();
        } catch (DatabaseException $exception) {
            throw PersistenceException::deleteFailed(entity: $metadata->entity, previous: $exception);
        }

        if ($affected > 1) {
            throw PersistenceException::unexpectedAffectedRows(
                entity: $metadata->entity,
                operation: 'delete',
                expectedMaximum: 1,
                actual: $affected,
            );
        }

        return $affected;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     */
    private function assertRelationsInitialized(EntityMetadata $metadata, object $entity): void
    {
        foreach ($metadata->relations as $relation) {
            if (!$relation instanceof BelongsToOneMetadata) {
                continue;
            }

            if ($this->property(entity: $metadata->entity, property: $relation->property)->isInitialized($entity)) {
                continue;
            }

            throw PersistenceException::uninitializedProperty(entity: $metadata->entity, property: $relation->property);
        }
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return array<string, string|int|float|bool|null>
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function relationValues(EntityMetadata $metadata, object $entity): array
    {
        $values = [];

        foreach ($metadata->relations as $relation) {
            if (!$relation instanceof BelongsToOneMetadata) {
                continue;
            }

            $reflection = $this->property(entity: $metadata->entity, property: $relation->property);

            if (!$reflection->isInitialized($entity)) {
                continue;
            }

            $values[$relation->foreignKey] = $this->relationValue(
                metadata: $metadata,
                relation: $relation,
                related: $reflection->getRawValue($entity),
            );
        }

        return $values;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function relationValue(
        EntityMetadata $metadata,
        BelongsToOneMetadata $relation,
        mixed $related,
    ): string|int|float|bool|null {
        if ($related === null) {
            if (!$relation->nullable) {
                throw PersistenceException::nullNotAllowed(entity: $metadata->entity, property: $relation->property);
            }

            return null;
        }

        if (!$related instanceof $relation->target) {
            throw PersistenceException::invalidRelation(
                entity: $metadata->entity,
                relation: $relation->property,
                expected: $relation->target,
                actual: get_debug_type($related),
            );
        }

        $identifier = $this->metadata->for($relation->target)->identifier->single();

        $reflection = $this->property(entity: $relation->target, property: $identifier->property);

        $value = $reflection->isInitialized($related) ? $reflection->getRawValue($related) : null;

        if ($value === null) {
            throw PersistenceException::unsavedRelation(
                entity: $metadata->entity,
                relation: $relation->property,
                target: $relation->target,
            );
        }

        return $identifier->single()->toDatabase($value);
    }

    /**
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function databaseValue(
        object $entity,
        EntityMetadata $metadata,
        PropertyMetadata $property,
    ): string|int|float|bool|null {
        $reflection = $this->property(entity: $metadata->entity, property: $property->property);

        if (!$reflection->isInitialized($entity)) {
            throw PersistenceException::uninitializedProperty(entity: $metadata->entity, property: $property->property);
        }

        $value = $reflection->getRawValue($entity);

        if ($value === null) {
            if (!$property->nullable) {
                throw PersistenceException::nullNotAllowed(entity: $metadata->entity, property: $property->property);
            }

            return null;
        }

        return $property->single()->toDatabase($value);
    }

    /**
     * @param class-string $entity
     *
     * @throws PersistenceException
     */
    private function property(string $entity, string $property): ReflectionProperty
    {
        if (isset($this->properties[$entity][$property])) {
            return $this->properties[$entity][$property];
        }

        try {
            return $this->properties[$entity][$property] = new ReflectionClass($entity)->getProperty($property);
        } catch (ReflectionException $exception) {
            throw PersistenceException::unknownProperty(entity: $entity, property: $property, previous: $exception);
        }
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     */
    private function assertEntity(EntityMetadata $metadata, object $entity): void
    {
        if ($entity instanceof $metadata->entity) {
            return;
        }

        throw PersistenceException::invalidEntity(expected: $metadata->entity, actual: $entity::class);
    }
}
