<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Collection\Collection;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Persistence\EntityPersister;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\InvalidEntityException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\EntityNotFoundException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

/**
 * @template T of object
 */
final readonly class EntityStore
{
    /**
     * @param EntityMetadata<T> $metadata
     */
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private Hydrator $hydrator,
        private TypeRegistry $types,
        private EntityPersister $persister,
    ) {}

    /**
     * @return EntityQuery<T>
     */
    public function query(): EntityQuery
    {
        return new EntityQuery(
            metadata: $this->metadata,
            hydrator: $this->hydrator,
            types: $this->types,
            builder: $this->database->table($this->metadata->table),
        );
    }

    /**
     * @return T|null
     *
     * @throws EntityDatabaseException
     * @throws InvalidIdentifierException
     * @throws TypeConversionException
     * @throws EntityNotFoundException
     * @throws HydrationException
     * @throws MappingException
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
     * @throws MappingException
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
    public function insert(object $entity): ?string
    {
        $this->assertEntity($entity);

        return $this->persister->insert(database: $this->database, metadata: $this->metadata, entity: $entity);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     */
    public function update(object $entity): int
    {
        $this->assertEntity($entity);

        return $this->persister->update(database: $this->database, metadata: $this->metadata, entity: $entity);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     */
    public function delete(object $entity): bool
    {
        $this->assertEntity($entity);

        return $this->persister->delete(database: $this->database, metadata: $this->metadata, entity: $entity);
    }

    /**
     * @throws InvalidEntityException
     */
    private function assertEntity(object $entity): void
    {
        if ($entity instanceof $this->metadata->entity) {
            return;
        }

        throw InvalidEntityException::forEntityStore(expected: $this->metadata->entity, actual: $entity::class);
    }

    /**
     * @throws TypeConversionException
     * @throws InvalidIdentifierException
     * @throws MappingException
     */
    private function applyIdentifier(EntityQuery $query, mixed $identifier): void
    {
        $primary = $this->metadata->identifier;

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
     * @throws MappingException
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
