<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

final readonly class MetadataFactory
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     */
    public function create(string $entity): EntityMetadata
    {
        return new EntityMetadata($entity); // todo other properties
    }
}
