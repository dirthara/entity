<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final class RelationIdentity
{
    /**
     * @var array<class-string, ReflectionProperty>
     */
    private array $identifiers = [];

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws ReflectionException
     */
    public function owner(EntityMetadata $metadata, object $entity): string|int|float|bool
    {
        $value = $this->value(metadata: $metadata, entity: $entity);

        if ($value === null) {
            throw PersistenceException::uninitializedProperty(
                entity: $metadata->entity,
                property: $metadata->identifier->single()->property,
            );
        }

        return $value;
    }

    /**
     * @param class-string $entity
     *
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws ReflectionException
     */
    public function related(
        EntityMetadata $target,
        object $related,
        string $entity,
        string $relation,
    ): string|int|float|bool {
        $value = $this->value(metadata: $target, entity: $related);

        if ($value === null) {
            throw PersistenceException::unsavedRelation(entity: $entity, relation: $relation, target: $target->entity);
        }

        return $value;
    }

    /**
     * @param class-string $entity
     *
     * @throws PersistenceException
     */
    public function assertTarget(RelationMetadata $relation, string $entity, object $related): void
    {
        $actual = $related::class;

        if ($related instanceof $relation->target) {
            return;
        }

        throw PersistenceException::invalidRelation(
            entity: $entity,
            relation: $relation->property,
            expected: $relation->target,
            actual: $actual,
        );
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws ReflectionException
     */
    private function value(EntityMetadata $metadata, object $entity): string|int|float|bool|null
    {
        $identifier = $metadata->identifier->single();

        $reflection =
            $this->identifiers[$metadata->entity] ??= new ReflectionClass($metadata->entity)->getProperty($identifier->property);

        $value = $reflection->isInitialized($entity) ? $reflection->getRawValue($entity) : null;

        return $value === null ? null : $identifier->single()->toDatabase($value);
    }
}
