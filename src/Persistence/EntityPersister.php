<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Exception\TypeConversionException;

interface EntityPersister
{
    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function insert(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): void;

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function update(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int;

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function delete(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int;
}
