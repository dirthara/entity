<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\TypeConversionException;

interface Hydrator
{
    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return T
     *
     * @throws CreateEntityException
     */
    public function newInstance(EntityMetadata $metadata): object;

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param array<string, mixed> $data
     *
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function hydrate(EntityMetadata $metadata, object $entity, array $data): void;
}
