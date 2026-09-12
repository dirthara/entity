<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;

interface EntityPersister
{
    public function insert(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): ?string;

    public function update(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): int;

    public function delete(ConnectedDatabase $database, EntityMetadata $metadata, object $entity): bool;
}
