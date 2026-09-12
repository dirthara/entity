<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Database\Database;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Persistence\EntityPersister;
use Dirthara\Entity\Exceptions\EntityNotFoundException;

/**
 * @template T of object
 */
final readonly class EntitySet
{
    private EntityQuery $query;

    public function __construct(
        private Database $database,
        private EntityMetadata $metadata,
        private Hydrator $hydrator,
        private EntityPersister $persister,
    ) {
        $this->query = new EntityQuery(
            entity: $this->metadata,
            queryBuilder: $this->database->table($this->metadata->table),
        );
    }

    public function query(): EntityQuery
    {
        return $this->query; //  todo should this return a new instance?
    }

    /**
     * @return T|null
     */
    public function find(mixed $id): ?object
    {
        $result = $this->query()->where($this->metadata->primaryKey, '=', $id)->first();

        if ($result === null) {
            return null;
        }

        $entity = $this->metadata->newInstance();

        $this->hydrator->hydrate($entity, $result);

        return $entity;
    }

    /**
     * @return T
     *
     * @throws EntityNotFoundException
     */
    public function findOrFail(mixed $id): object
    {
        // ...
    }

    /**
     * @return Collection<int, T>
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    /**
     * @param T $entity
     */
    public function insert(object $entity): void
    {
        // ...
    }

    /**
     * @param T $entity
     */
    public function update(object $entity): void
    {
        // ...
    }

    /**
     * @param T $entity
     */
    public function save(object $entity): void
    {
        // ...
    }

    /**
     * @param T $entity
     */
    public function delete(object $entity): void
    {
        // ...
    }
}
