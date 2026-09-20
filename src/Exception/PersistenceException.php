<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;

final class PersistenceException extends EntityException
{
    public static function invalidEntity(string $expected, string $actual): self
    {
        return new self(sprintf('Invalid entity type, expected "%s", got "%s"', $expected, $actual))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function uninitializedProperty(string $entity, string $property): self
    {
        return new self(sprintf('Property "%s" of entity "%s" is not initialized', $property, $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function unknownProperty(string $entity, string $property, Throwable $previous): self
    {
        return new self(
            message: sprintf('Unknown property "%s" in entity "%s"', $property, $entity),
            previous: $previous,
        )->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function nullNotAllowed(string $entity, string $property): self
    {
        return new self(sprintf(
            'Null value not allowed for property "%s" in entity "%s"',
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function unsavedRelation(string $entity, string $relation, string $target): self
    {
        return new self(sprintf(
            'Relation "%s" of entity "%s" points at a "%s" that has no identifier yet',
            $relation,
            $entity,
            $target,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'target' => $target,
        ]);
    }

    public static function invalidRelation(string $entity, string $relation, string $expected, string $actual): self
    {
        return new self(sprintf(
            'Relation "%s" of entity "%s" expects a "%s", got "%s"',
            $relation,
            $entity,
            $expected,
            $actual,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function relationNotNullable(string $entity, string $relation): self
    {
        return new self(sprintf('Relation "%s" of entity "%s" does not accept null', $relation, $entity))->addContext([
            'entity' => $entity,
            'relation' => $relation,
        ]);
    }

    public static function relationWriteFailed(string $entity, string $relation, Throwable $previous): self
    {
        return new self(
            message: sprintf('Failed to write relation "%s" of entity "%s"', $relation, $entity),
            previous: $previous,
        )->addContext([
            'entity' => $entity,
            'relation' => $relation,
        ]);
    }

    public static function unsupportedRelation(string $entity, string $relation, string $kind): self
    {
        return new self(sprintf(
            'Relation "%s" of entity "%s" is of unsupported kind "%s"',
            $relation,
            $entity,
            $kind,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'kind' => $kind,
        ]);
    }

    public static function missingGeneratedIdentifier(string $entity, string $property): self
    {
        return new self(sprintf(
            'The database returned no generated identifier for property "%s" in entity "%s"',
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function insertFailed(string $entity, Throwable $previous): self
    {
        return new self(message: sprintf('Failed to insert entity "%s"', $entity), previous: $previous)->addContext([
            'entity' => $entity,
        ]);
    }

    public static function updateFailed(string $entity, Throwable $previous): self
    {
        return new self(message: sprintf('Failed to update entity "%s"', $entity), previous: $previous)->addContext([
            'entity' => $entity,
        ]);
    }

    public static function deleteFailed(string $entity, Throwable $previous): self
    {
        return new self(message: sprintf('Failed to delete entity "%s"', $entity), previous: $previous)->addContext([
            'entity' => $entity,
        ]);
    }

    public static function unexpectedAffectedRows(
        string $entity,
        string $operation,
        int $expectedMaximum,
        int $actual,
    ): self {
        return new self(message: sprintf(
            'Unexpected affected rows "%s" for operation "%s" on entity "%s"',
            $actual,
            $operation,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'operation' => $operation,
            'expectedMaximum' => $expectedMaximum,
            'actual' => $actual,
        ]);
    }
}
