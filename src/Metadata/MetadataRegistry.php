<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\TypeConversionException;

final class MetadataRegistry
{
    /**
     * @var array<class-string, EntityMetadata>
     */
    private array $metadata = [];

    public function __construct(
        private readonly MetadataFactory $factory,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function for(string $entity): EntityMetadata
    {
        $this->metadata[$entity] ??= $this->factory->create($entity);

        // @mago-expect lint:inline-variable-return
        /** @var EntityMetadata<T> $metadata */
        $metadata = $this->metadata[$entity];

        return $metadata;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     */
    public function has(string $entity): bool
    {
        return isset($this->metadata[$entity]);
    }

    public function clear(): void
    {
        $this->metadata = [];
    }
}
