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
