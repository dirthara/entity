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

    /**
     * @param list<string> $relations
     */
    public static function cursorCannotLoadRelations(string $entity, array $relations): self
    {
        return new self(sprintf(
            'Relations %s of entity "%s" cannot be loaded from a cursor',
            implode(', ', array_map(static fn(string $relation): string => sprintf('"%s"', $relation), $relations)),
            $entity,
        ))->addContext([
            'entity' => $entity,
            'relations' => $relations,
        ]);
    }

    public static function invalidRelatedValue(string $entity, string $relation, string $actual): self
    {
        return new self(sprintf(
            'Relation "%s" of entity "%s" holds a "%s" where an entity was expected',
            $relation,
            $entity,
            $actual,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
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
            'relation' => $relation,
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
            'relation' => $relation,
        ]);
    }

    public static function relatedEntityNotFound(string $entity, string $relation, string|int $identifier): self
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

    public static function multipleRelatedEntities(string $entity, string $relation, string|int $identifier): self
    {
        return new self(sprintf(
            'Multiple related entities found for relation "%s" in entity "%s" with identifier "%s"',
            $relation,
            $entity,
            $identifier,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
            'identifier' => $identifier,
        ]);
    }

    public static function uninitializedIdentifier(string $entity, string $property): self
    {
        return new self(sprintf('Identifier "%s" for entity "%s" is not initialized', $property, $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function nullIdentifier(string $entity, string $property): self
    {
        return new self(sprintf('Identifier "%s" for entity "%s" is null', $property, $entity))->addContext([
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

    public static function invalidIdentifierValue(mixed $value): self
    {
        return new self(sprintf('Invalid identifier value of type "%s"', get_debug_type($value)))->addContext([
            'value' => $value,
        ]);
    }
}
