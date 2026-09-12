<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exceptions\CreateEntityException;

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

    public function hydrate(object $entity, array $data): void;
}
