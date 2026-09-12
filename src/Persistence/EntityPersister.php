<?php

declare(strict_types=1);

namespace Dirthara\Entity\Persistence;

use Dirthara\Entity\Metadata\EntityMetadata;

interface EntityPersister
{
    public function insert(EntityMetadata $metadata, object $entity): mixed;

    public function update(EntityMetadata $metadata, object $entity): mixed;

    public function delete(EntityMetadata $metadata, object $entity): mixed;
}
