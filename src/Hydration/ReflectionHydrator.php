<?php

declare(strict_types=1);

namespace Dirthara\Entity\Hydration;

use TypeError;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\TypeConversionException;

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
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @return T
     *
     * @throws CreateEntityException
     */
    public function newInstance(EntityMetadata $metadata): object
    {
        try {
            /** @var T $entity */
            $entity = $this->reflection($metadata->entity)->newInstanceWithoutConstructor();
        } catch (ReflectionException $exception) {
            throw CreateEntityException::fromReflection(exception: $exception, entity: $metadata->entity);
        }

        return $entity;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     * @param array<string, mixed> $data
     *
     * @throws HydrationException
     * @throws TypeConversionException
     * @throws CreateEntityException
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
     * @param array<string, mixed> $data
     *
     * @throws HydrationException
     * @throws TypeConversionException
     * @throws CreateEntityException
     */
    private function hydrateProperty(
        object $entity,
        EntityMetadata $metadata,
        PropertyMetadata $property,
        array $data,
    ): void {
        $column = $property->column();

        if (!array_key_exists($column, $data)) {
            throw HydrationException::missingColumn(
                entity: $metadata->entity,
                property: $property->property,
                column: $column,
            );
        }

        $value = $this->value(metadata: $metadata, property: $property, value: $data[$column]);

        $reflection = $this->reflectionProperty($metadata->entity, $property->property);

        // @mago-expect analysis:avoid-catching-error
        try {
            $reflection->setValue($entity, $value);
        } catch (TypeError $exception) {
            throw HydrationException::propertyFailed(
                entity: $metadata->entity,
                property: $property->property,
                previous: $exception,
            );
        }
    }

    /**
     * @throws HydrationException
     */
    private function value(EntityMetadata $metadata, PropertyMetadata $property, mixed $value): mixed
    {
        if ($value !== null) {
            return $property->single()->fromDatabase($value);
        }

        if ($property->nullable) {
            return null;
        }

        throw HydrationException::nullNotAllowed(
            entity: $metadata->entity,
            property: $property->property,
            column: $property->column(),
        );
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
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
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
