<?php

declare(strict_types=1);

namespace Dirthara\Entity;

use Dirthara\Database\Database;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Entity\Relation\Relations;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Persistence\EntityPersister;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final readonly class EntityManager
{
    public function __construct(
        private Database $database,
        private MetadataRegistry $metadata,
        private Hydrator $hydrator,
        private EntityPersister $persister,
        private Relations $relations,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @return EntityStore<T>
     *
     * @throws EntityDatabaseException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function of(string $entity, ?string $connection = null): EntityStore
    {
        $metadata = $this->metadata->for($entity);

        try {
            return new EntityStore(
                database: $this->database->using($connection ?? $metadata->connection),
                metadata: $metadata,
                hydrator: $this->hydrator,
                persister: $this->persister,
                relations: $this->relations,
            );
        } catch (ConnectionException $exception) {
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $entity,
                operation: 'connect',
            );
        }
    }
}
