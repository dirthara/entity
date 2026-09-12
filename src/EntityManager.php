<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Database\Database;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Persistence\EntityPersister;

final readonly class EntityManager
{
    public function __construct(
        private Database $database,
        private MetadataRegistry $metadata,
        private Hydrator $hydrator,
        private TypeRegistry $types,
        private EntityPersister $persister,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @return EntityStore<T>
     */
    public function of(string $entity): EntityStore
    {
        $metadata = $this->metadata->for($entity);

        return new EntityStore(
            database: $this->database,
            metadata: $metadata,
            hydrator: $this->hydrator,
            types: $this->types,
            persister: $this->persister,
        );
    }
}
