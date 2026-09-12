<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Database\Database;
use Dirthara\Collection\Collection;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Persistence\EntityPersister;
use Dirthara\Entity\Exceptions\HydrationException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exceptions\CreateEntityException;
use Dirthara\Entity\Exceptions\EntityMappingException;
use Dirthara\Entity\Exceptions\InvalidEntityException;
use Dirthara\Entity\Exceptions\EntityDatabaseException;
use Dirthara\Entity\Exceptions\EntityNotFoundException;
use Dirthara\Entity\Exceptions\TypeConversionException;
use Dirthara\Entity\Exceptions\InvalidIdentifierException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

/**
 * @template T of object
 */
final readonly class EntityStore
{
    /**
     * @param EntityMetadata<T> $metadata
     */
    public function __construct(
        private Database $database,
        private EntityMetadata $metadata,
        private Hydrator $hydrator,
        private TypeRegistry $types,
        private EntityPersister $persister,
    ) {}

    /**
     * @return EntityQuery<T>
     *
     * @throws EntityDatabaseException
     */
    public function query(): EntityQuery
    {
        try {
            return new EntityQuery(
                metadata: $this->metadata,
                hydrator: $this->hydrator,
                types: $this->types,
                builder: $this->database->table($this->metadata->table),
            );
        } catch (ConnectionException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }
    }

    /**
     * @return T|null
     *
     * @throws EntityDatabaseException
     * @throws InvalidIdentifierException
     * @throws EntityMappingException
     * @throws TypeConversionException
     * @throws EntityNotFoundException
     * @throws HydrationException
     */
    public function find(mixed $identifier, bool $throw = false): ?object
    {
        $query = $this->query();

        $this->applyIdentifier(query: $query, identifier: $identifier);

        try {
            return $query->first();
        } catch (CreateEntityException $exception) {
            if ($throw) {
                throw EntityNotFoundException::forIdentifier(
                    identifier: $identifier,
                    entity: $this->metadata->entity,
                    previous: $exception,
                );
            }

            return null;
        }
    }

    /**
     * @return T
     *
     * @throws EntityDatabaseException
     * @throws EntityNotFoundException
     * @throws InvalidIdentifierException
     * @throws EntityMappingException
     * @throws TypeConversionException
     * @throws HydrationException
     */
    public function findOrFail(mixed $identifier): object
    {
        return $this->find($identifier, true);
    }

    /**
     * @return Collection<T>
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     * @throws TypeConversionException
     * @throws HydrationException
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    /**
     * @throws EntityDatabaseException
     */
    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * @throws EntityDatabaseException
     */
    public function exists(): bool
    {
        return $this->query()->exists();
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     */
    public function insert(object $entity): mixed
    {
        $this->assertEntity($entity);

        return $this->persister->insert(metadata: $this->metadata, entity: $entity);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     */
    public function update(object $entity): mixed
    {
        $this->assertEntity($entity);

        return $this->persister->update(metadata: $this->metadata, entity: $entity);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     */
    public function delete(object $entity): mixed
    {
        $this->assertEntity($entity);

        return $this->persister->delete(metadata: $this->metadata, entity: $entity);
    }

    /**
     * @throws InvalidEntityException
     */
    private function assertEntity(object $entity): void
    {
        if ($entity instanceof $this->metadata->entity) {
            return;
        }

        throw InvalidEntityException::forEntitySet(expected: $this->metadata->entity, actual: $entity::class);
    }

    /**
     * @throws EntityMappingException
     * @throws TypeConversionException
     * @throws InvalidIdentifierException
     */
    private function applyIdentifier(EntityQuery $query, mixed $identifier): void
    {
        $primary = $this->metadata->primaryKey;

        if ($primary->isSingle()) {
            $property = $primary->single();

            $query->where(property: $property->property, operator: ComparisonOperator::Equal, value: $identifier);

            return;
        }

        if (!is_array($identifier)) {
            throw InvalidIdentifierException::compositeExpected(entity: $this->metadata->entity);
        }

        foreach ($primary->properties as $property) {
            $this->applyCompositeIdentifierProperty(query: $query, property: $property, identifier: $identifier);
        }
    }

    /**
     * @param array<string, mixed> $identifier
     *
     * @throws InvalidIdentifierException
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    private function applyCompositeIdentifierProperty(
        EntityQuery $query,
        PropertyMetadata $property,
        array $identifier,
    ): void {
        if (!array_key_exists($property->property, $identifier)) {
            throw InvalidIdentifierException::missingProperty(
                entity: $this->metadata->entity,
                property: $property->property,
            );
        }

        $query->where(
            property: $property->property,
            operator: ComparisonOperator::Equal,
            value: $identifier[$property->property],
        );
    }
}
