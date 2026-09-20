<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;

final class RelationLoadingException extends EntityException
{
    public static function missingForeignKeyColumn(string $entity, string $relation, string $column): self
    {
        return new self(sprintf(
            'Missing foreign key column "%s" for relation "%s" in entity "%s"',
            $column,
            $relation,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'column' => $column,
        ]);
    }

    public static function invalidEntity(string $expected, string $actual): self
    {
        return new self(sprintf('Expected entity "%s" but got "%s"', $expected, $actual))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function unsupportedRelation(string $relation): self
    {
        return new self(sprintf('Unsupported relation "%s"', $relation))->addContext([
            'relation' => $relation,
        ]);
    }

    public static function foreignKeyNotCaptured(string $entity, string $relation): self
    {
        return new self(sprintf(
            'Foreign key not captured for relation "%s" in entity "%s"',
            $relation,
            $entity,
        ))->addContext([
            'entity' => $entity,
        ]);
    }

    public static function nullRelationNotAllowed(string $entity, string $relation): self
    {
        return new self(sprintf(
            'Null relation not allowed for relation "%s" in entity "%s"',
            $relation,
            $entity,
        ))->addContext([
            'entity' => $entity,
        ]);
    }

    public static function relatedEntityNotFound(string $entity, string $relation, string $identifier): self
    {
        return new self(sprintf(
            'Related entity "%s" not found for relation "%s" with identifier "%s"',
            $entity,
            $relation,
            $identifier,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'identifier' => $identifier,
        ]);
    }

    public static function multipleRelatedEntities(string $entity, string $relation, string $identifier): self
    {
        return new self(sprintf(
            'Multiple related entities found for relation "%s" with identifier "%s"',
            $relation,
            $identifier,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'identifier' => $identifier,
        ]);
    }

    public static function uninitializedIdentifier(string $entity, string $property): self
    {
        return new self(sprintf('Identifier for "%s" is not initialized', $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function nullIdentifier(string $entity, string $property): self
    {
        return new self(sprintf('Identifier for "%s" is null', $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function compositeIdentifier(string $entity): self
    {
        return new self(sprintf('Composite identifier not supported for "%s"', $entity))->addContext([
            'entity' => $entity,
        ]);
    }

    public static function assignmentFailed(string $entity, string $relation, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to assign relation "%s" to entity "%s"', $relation, $entity),
            previous: $previous,
        )->addContext([
            'entity' => $entity,
            'relation' => $relation,
        ]);
    }

    public static function unknownProperty(string $entity, string $property, Throwable $previous): self
    {
        return new self(
            sprintf('Unknown property "%s" in entity "%s"', $property, $entity),
            previous: $previous,
        )->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function reflectionFailed(string $entity, Throwable $previous): self
    {
        return new self(sprintf('Failed to reflect entity "%s"', $entity), previous: $previous)->addContext([
            'entity' => $entity,
        ]);
    }

    public static function invalidIdentifierValue(mixed $value): self
    {
        return new self(sprintf('Invalid identifier value: %s', $value))->addContext([
            'value' => $value,
        ]);
    }
}
