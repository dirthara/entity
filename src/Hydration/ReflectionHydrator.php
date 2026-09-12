<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use ReflectionClass;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exceptions\CreateEntityException;

final class ReflectionHydrator implements Hydrator
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $reflections = [];

    /**
     * @template T of object
     *
     * @return T
     *
     * @throws CreateEntityException
     */
    public function newInstance(EntityMetadata $metadata): object
    {
        try {
            $reflection = $this->reflections[$metadata->entity] ??= new ReflectionClass($metadata->entity);

            return $reflection->newInstanceWithoutConstructor();
        } catch (\ReflectionException $exception) {
            throw CreateEntityException::fromReflection($exception, $metadata->entity);
        }
    }

    public function hydrate(object $entity, array $data): void
    {
        // TODO: Implement hydrate() method.
    }
}
