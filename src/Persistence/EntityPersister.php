<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;

interface EntityPersister
{
    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public function insert(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): void;

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public function update(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int;

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     */
    public function delete(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): bool;
}
