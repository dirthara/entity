<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;

final class ReflectionPersister implements EntityPersister
{
    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    /**
     * @throws PersistenceException
     */
    public function insert(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): ?string
    {
        $this->assertEntity($metadata, $entity);

        $values = [];

        foreach ($metadata->properties as $property) {
            if ($property->generated) {
                continue;
            }

            $values[$property->column] = $this->databaseValue(
                entity: $entity,
                metadata: $metadata,
                property: $property,
            );
        }

        try {
            return $database->table($metadata->table)->insertGetId($values);
        } catch (DatabaseException $exception) {
            throw PersistenceException::insertFailed(entity: $metadata->entity, previous: $exception);
        }
    }

    /**
     * @throws PersistenceException
     */
    public function update(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int
    {
        $this->assertEntity($metadata, $entity);

        $values = [];

        foreach ($metadata->properties as $property) {
            if ($property->identifier || $property->generated) {
                continue;
            }

            $values[$property->column] = $this->databaseValue(
                entity: $entity,
                metadata: $metadata,
                property: $property,
            );
        }

        if ($values === []) {
            return 0;
        }

        $query = $database->table($metadata->table);

        foreach ($metadata->identifier->properties as $property) {
            $query->where(
                $property->column,
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
     * @throws PersistenceException
     */
    public function delete(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): bool
    {
        $this->assertEntity($metadata, $entity);

        $query = $database->table($metadata->table);

        foreach ($metadata->identifier->properties as $property) {
            $query->where(
                $property->column,
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

        return $affected === 1;
    }

    /**
     * @throws PersistenceException
     */
    private function databaseValue(object $entity, EntityMetadata $metadata, PropertyMetadata $property): mixed
    {
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

        return $property->converter->toDatabase($value);
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
