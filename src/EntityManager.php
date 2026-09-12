<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Database\Database;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Persistence\EntityPersister;

final readonly class EntityManager
{
    public function __construct(
        private Database $database,
        private MetadataRegistry $metadata,
        private Hydrator $hydrator,
        private EntityPersister $persister,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @return EntitySet<T>
     */
    public function of(string $entity): EntitySet
    {
        $metadata = $this->metadata->for($entity);

        return new EntitySet(
            database: $this->database,
            metadata: $metadata,
            hydrator: $this->hydrator,
            persister: $this->persister,
        );
    }
}
