<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use ReflectionProperty;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Persistence\EntityPersister;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Relation\Handle\HasOneHandle;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Relation\Handle\HasManyHandle;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Relation\Handle\RelationHandle;
use Dirthara\Entity\Relation\RelationHandleFactory;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\InvalidEntityException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\EntityNotFoundException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Relation\Handle\BelongsToOneHandle;
use Dirthara\Entity\Relation\Handle\BelongsToManyHandle;
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
        private EntityPersister $persister,
        private RelationLoader $relationLoader,
        private RelationStateRegistry $relationStates,
        private RelationHandleFactory $relationHandles,
    ) {}

    /**
     * @return EntityQuery<T>
     */
    public function query(): EntityQuery
    {
        return new EntityQuery(
            metadata: $this->metadata,
            hydrator: $this->hydrator,
            builder: $this->database->table($this->metadata->table),
            relationLoader: $this->relationLoader,
            database: $this->database,
            relationStates: $this->relationStates,
        );
    }

    /**
     * @return T|null
     *
     * @throws EntityDatabaseException
     * @throws InvalidIdentifierException
     * @throws TypeConversionException
     * @throws CreateEntityException
     * @throws HydrationException
     * @throws MappingException
     */
    public function find(mixed $identifier): ?object
    {
        $query = $this->query();

        $this->applyIdentifier(query: $query, identifier: $identifier);

        return $query->first();
    }

    /**
     * @return T
     *
     * @throws EntityDatabaseException
     * @throws EntityNotFoundException
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws TypeConversionException
     * @throws CreateEntityException
     * @throws HydrationException
     */
    public function findOrFail(mixed $identifier): object
    {
        return (
            $this->find($identifier) ?? throw EntityNotFoundException::forIdentifier(
                identifier: $identifier,
                entity: $this->metadata->entity,
            )
        );
    }

    /**
     * @return Collection<int, T>
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
     * @param T $entity
     * @param list<string> $relations
     * @param list<string> $without
     *
     * @throws InvalidEntityException
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function load(object $entity, array $relations = [], array $without = []): void
    {
        $this->assertEntity($entity);

        $this->relationLoader->assertLoadable(metadata: $this->metadata, relations: $relations);
        $this->relationLoader->assertLoadable(metadata: $this->metadata, relations: $without);

        $this->relationLoader->load(
            database: $this->database,
            metadata: $this->metadata,
            entities: [$entity],
            relations: $relations,
            without: $without,
        );
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function relation(object $entity, string $relation): RelationHandle
    {
        $this->assertEntity($entity);

        return $this->relationHandles->handle(
            database: $this->database,
            metadata: $this->metadata,
            entity: $entity,
            relation: $relation,
        );
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function belongsToOne(object $entity, string $relation): BelongsToOneHandle
    {
        return $this->handle(entity: $entity, relation: $relation, handle: BelongsToOneHandle::class);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function hasOne(object $entity, string $relation): HasOneHandle
    {
        return $this->handle(entity: $entity, relation: $relation, handle: HasOneHandle::class);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function hasMany(object $entity, string $relation): HasManyHandle
    {
        return $this->handle(entity: $entity, relation: $relation, handle: HasManyHandle::class);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function belongsToMany(object $entity, string $relation): BelongsToManyHandle
    {
        return $this->handle(entity: $entity, relation: $relation, handle: BelongsToManyHandle::class);
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
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function insert(object $entity): void
    {
        $this->assertEntity($entity);

        $this->persister->insert(database: $this->database, metadata: $this->metadata, entity: $entity);

        $this->markWrittenRelations($entity);
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function update(object $entity): int
    {
        $this->assertEntity($entity);

        $affected = $this->persister->update(database: $this->database, metadata: $this->metadata, entity: $entity);

        $this->markWrittenRelations($entity);

        return $affected;
    }

    /**
     * @param T $entity
     *
     * @throws InvalidEntityException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function delete(object $entity): int
    {
        $this->assertEntity($entity);

        return $this->persister->delete(database: $this->database, metadata: $this->metadata, entity: $entity);
    }

    /**
     * @template THandle of RelationHandle
     *
     * @param T $entity
     * @param class-string<THandle> $handle
     *
     * @return THandle
     *
     * @throws InvalidEntityException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function handle(object $entity, string $relation, string $handle): RelationHandle
    {
        $resolved = $this->relation(entity: $entity, relation: $relation);

        if ($resolved instanceof $handle) {
            return $resolved;
        }

        throw PersistenceException::unexpectedRelationHandle(
            entity: $this->metadata->entity,
            relation: $relation,
            expected: $handle,
            actual: $resolved::class,
        );
    }

    private function markWrittenRelations(object $entity): void
    {
        foreach ($this->metadata->relations as $relation) {
            if (!$relation instanceof BelongsToOneMetadata) {
                continue;
            }

            if (!new ReflectionProperty($entity, $relation->property)->isInitialized($entity)) {
                continue;
            }

            $this->relationStates->markLoaded(entity: $entity, relation: $relation->property);
        }
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
     * @param array<array-key, mixed> $identifier
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
