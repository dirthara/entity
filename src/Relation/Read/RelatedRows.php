<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Read;

use TypeError;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use ReflectionNamedType;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final class RelatedRows
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $classes = [];

    /**
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private array $properties = [];

    public function __construct(
        private readonly MetadataRegistry $metadata,
        private readonly Hydrator $hydrator,
        private readonly RelationStateRegistry $states,
    ) {}

    /**
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function targetOf(RelationMetadata $relation): EntityMetadata
    {
        return $this->metadata->for($relation->target);
    }

    /**
     * @throws RelationLoadingException
     * @throws InvalidIdentifierException
     */
    public function singleIdentifier(EntityMetadata $metadata): PropertyMetadata
    {
        if (!$metadata->identifier->isSingle()) {
            throw RelationLoadingException::compositeIdentifier(entity: $metadata->entity);
        }

        $identifier = $metadata->identifier->single();

        if (!$identifier->isSingle()) {
            throw RelationLoadingException::compositeIdentifier(entity: $metadata->entity);
        }

        return $identifier;
    }

    /**
     * @param list<object> $entities
     *
     * @return array{
     *     0: array<array-key, string|int|float|bool>,
     *     1: array<array-key, list<object>>
     * }
     *
     * @throws RelationLoadingException
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function groupByIdentifier(EntityMetadata $metadata, array $entities): array
    {
        $identifiers = [];
        $entitiesByIdentifier = [];

        foreach ($entities as $entity) {
            $identifier = $this->identifierValue(metadata: $metadata, entity: $entity);

            $key = $this->key($identifier);

            $identifiers[$key] = $identifier;
            $entitiesByIdentifier[$key][] = $entity;
        }

        return [$identifiers, $entitiesByIdentifier];
    }

    /**
     * @throws RelationLoadingException
     */
    public function capturedForeignKey(object $entity, RelationMetadata $relation): mixed
    {
        return $this->states->foreignKey(entity: $entity, relation: $relation->property);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws TypeConversionException
     * @throws CreateEntityException
     * @throws HydrationException
     * @throws RelationLoadingException
     * @throws MappingException
     */
    public function hydrate(EntityMetadata $metadata, array $row): object
    {
        $entity = $this->hydrator->newInstance($metadata);

        $this->hydrator->hydrate(metadata: $metadata, entity: $entity, data: $row);

        $this->states->capture(metadata: $metadata, entity: $entity, row: $row);

        return $entity;
    }

    /**
     * @throws RelationLoadingException
     */
    public function assign(object $entity, RelationMetadata $relation, mixed $value): void
    {
        $property = $this->property(entity: $entity::class, property: $relation->property);

        try {
            $property->setRawValue(object: $entity, value: $this->writable(property: $property, value: $value));
        } catch (TypeError $exception) {
            throw RelationLoadingException::assignmentFailed(
                entity: $entity::class,
                relation: $relation->property,
                previous: $exception,
            );
        }

        $this->states->markLoaded(entity: $entity, relation: $relation->property);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws RelationLoadingException
     */
    public function rowValue(array $row, string $column, string $entity, string $relation): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw RelationLoadingException::missingForeignKeyColumn(
                entity: $entity,
                relation: $relation,
                column: $column,
            );
        }

        return $row[$column];
    }

    /**
     * @throws RelationLoadingException
     */
    public function key(mixed $value): string
    {
        return (string) $this->scalar($value);
    }

    /**
     * @throws RelationLoadingException
     */
    public function scalar(mixed $value): string|int|float|bool
    {
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw RelationLoadingException::invalidIdentifierValue(value: $value);
    }

    /**
     * @param list<object> $entities
     *
     * @return list<object>
     */
    public function distinct(array $entities): array
    {
        $unique = [];

        foreach ($entities as $entity) {
            $unique[spl_object_id($entity)] = $entity;
        }

        return array_values($unique);
    }

    /**
     * @param class-string $entity
     *
     * @throws RelationLoadingException
     */
    public function property(string $entity, string $property): ReflectionProperty
    {
        if (isset($this->properties[$entity][$property])) {
            return $this->properties[$entity][$property];
        }

        try {
            $reflection = $this->classes[$entity] ??= new ReflectionClass($entity);

            return $this->properties[$entity][$property] = $reflection->getProperty($property);
        } catch (ReflectionException $exception) {
            throw RelationLoadingException::unknownProperty(entity: $entity, property: $property, previous: $exception);
        }
    }

    /**
     * @throws MappingException
     * @throws RelationLoadingException
     * @throws InvalidIdentifierException
     * @throws TypeConversionException
     */
    private function identifierValue(EntityMetadata $metadata, object $entity): string|int|float|bool
    {
        $identifier = $this->singleIdentifier($metadata);

        $reflection = $this->property(entity: $metadata->entity, property: $identifier->property);

        if (!$reflection->isInitialized($entity)) {
            throw RelationLoadingException::uninitializedIdentifier(
                entity: $metadata->entity,
                property: $identifier->property,
            );
        }

        $value = $reflection->getRawValue($entity);

        if ($value === null) {
            throw RelationLoadingException::nullIdentifier(entity: $metadata->entity, property: $identifier->property);
        }

        return $this->scalar($identifier->single()->toDatabase($value));
    }

    private function writable(ReflectionProperty $property, mixed $value): mixed
    {
        if (!$value instanceof Collection) {
            return $value;
        }

        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            return $value->toArray();
        }

        return $value;
    }
}
