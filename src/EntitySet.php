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
    public function __construct(
        private Database $database,
        private EntityMetadata $metadata,
        private Hydrator $hydrator,
        private EntityPersister $persister,
    ) {}

    public function query(): EntityQuery
    {
        // @todo
    }

    /**
     * @return T|null
     */
    public function find(mixed $id): ?object
    {
        $result = $this->query()->where($this->metadata->primaryKey->single()->column, '=', $id)->first();

        if ($result === null) {
            return null;
        }

        $entity = $this->hydrator->newInstance($this->metadata);

        $this->hydrator->hydrate($this->metadata, $entity, $result);

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
