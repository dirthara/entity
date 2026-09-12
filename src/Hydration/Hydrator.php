<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exceptions\HydrationException;
use Dirthara\Entity\Exceptions\CreateEntityException;
use Dirthara\Entity\Exceptions\TypeConversionException;

interface Hydrator
{
    /**
     * @template T of object
     *
     * @return T
     *
     * @throws CreateEntityException
     */
    public function newInstance(EntityMetadata $metadata): object;

    /**
     * @throws HydrationException
     * @throws TypeConversionException
     */
    public function hydrate(EntityMetadata $metadata, object $entity, array $data): void;
}
