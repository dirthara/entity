<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use Throwable;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Exception\CreateEntityException;

final class ReflectionHydrator implements Hydrator
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $classes = [];

    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    /**
     * @throws CreateEntityException
     */
    public function newInstance(EntityMetadata $metadata): object
    {
        try {
            return $this->reflection($metadata->entity)->newInstanceWithoutConstructor();
        } catch (ReflectionException $exception) {
            throw CreateEntityException::fromReflection(exception: $exception, entity: $metadata->entity);
        }
    }

    /**
     * @throws HydrationException
     */
    public function hydrate(EntityMetadata $metadata, object $entity, array $data): void
    {
        $this->assertEntityMatchesMetadata($metadata, $entity);

        foreach ($metadata->properties as $property) {
            $this->hydrateProperty(entity: $entity, metadata: $metadata, property: $property, data: $data);
        }
    }

    /**
     * @param class-string $entity
     *
     * @return ReflectionClass<object>
     *
     * @throws CreateEntityException
     */
    private function reflection(string $entity): ReflectionClass
    {
        try {
            return $this->classes[$entity] ??= new ReflectionClass($entity);
        } catch (ReflectionException $exception) {
            throw CreateEntityException::fromReflection(exception: $exception, entity: $entity);
        }
    }

    /**
     * @throws HydrationException
     */
    private function hydrateProperty(
        object $entity,
        EntityMetadata $metadata,
        PropertyMetadata $property,
        array $data,
    ): void {
        if (!array_key_exists($property->column, $data)) {
            throw HydrationException::missingColumn(
                entity: $metadata->entity,
                property: $property->property,
                column: $property->column,
            );
        }

        $value = $property->converter->fromDatabase($data[$property->column]);

        try {
            $this->reflectionProperty($metadata->entity, $property->property)->setValue($entity, $value);
        } catch (Throwable $exception) {
            throw HydrationException::propertyFailed(
                entity: $metadata->entity,
                property: $property->property,
                previous: $exception,
            );
        }
    }

    /**
     * @param class-string $entity
     *
     * @throws CreateEntityException
     * @throws HydrationException
     */
    private function reflectionProperty(string $entity, string $property): ReflectionProperty
    {
        if (isset($this->properties[$entity][$property])) {
            return $this->properties[$entity][$property];
        }

        try {
            return $this->properties[$entity][$property] = $this->reflection($entity)->getProperty($property);
        } catch (ReflectionException $exception) {
            throw HydrationException::unknownProperty(entity: $entity, property: $property, previous: $exception);
        }
    }

    /**
     * @throws HydrationException
     */
    private function assertEntityMatchesMetadata(EntityMetadata $metadata, object $entity): void
    {
        if ($entity instanceof $metadata->entity) {
            return;
        }

        throw HydrationException::invalidEntity(expected: $metadata->entity, actual: $entity::class);
    }
}
