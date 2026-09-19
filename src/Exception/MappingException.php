<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use ReflectionException;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Ignore;
use Dirthara\Entity\Attribute\Generated;

final class MappingException extends EntityException
{
    public static function fromReflection(ReflectionException $exception, string $entity): self
    {
        return new self(
            message: sprintf('Failed to create mapping for entity "%s": %s', $entity, $exception->getMessage()),
            previous: $exception,
        )->addContext([
            'entity' => $entity,
        ]);
    }

    public static function invalidEntityType(string $entity, string $type): self
    {
        return new self(sprintf('Invalid entity type for "%s": %s', $entity, $type))->addContext([
            'entity' => $entity,
        ]);
    }

    public static function duplicateColumn(
        string $entity,
        string $column,
        string $firstProperty,
        string $secondProperty,
    ): self {
        return new self(sprintf(
            'Duplicate column "%s" in entity "%s" for properties "%s" and "%s"',
            $column,
            $entity,
            $firstProperty,
            $secondProperty,
        ))->addContext([
            'entity' => $entity,
            'column' => $column,
            'firstProperty' => $firstProperty,
            'secondProperty' => $secondProperty,
        ]);
    }

    public static function missingIdentifier(string $entity): self
    {
        return new self(sprintf('Missing identifier for entity "%s"', $entity))->addContext([
            'entity' => $entity,
        ]);
    }

    /**
     * @param list<class-string<Ignore|Id|Column|Generated|Column>> $attributes
     */
    public static function conflictingAttributes(string $entity, string $property, array $attributes): self
    {
        return new self(sprintf(
            'Conflicting attributes for property "%s" in entity "%s": %s',
            $property,
            $entity,
            implode(', ', $attributes),
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'attributes' => $attributes,
        ]);
    }

    public static function missingPropertyType(string $entity, string $property): self
    {
        return new self(sprintf(
            'Missing property type for property "%s" in entity "%s"',
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function unsupportedPropertyType(string $entity, string $property, string $type): self
    {
        return new self(sprintf(
            'Unsupported property type "%s" for property "%s" in entity "%s"',
            $type,
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'type' => $type,
        ]);
    }

    /**
     * @param class-string $entity
     * @param class-string $converter
     */
    public static function unconstructableConverter(
        string $entity,
        string $property,
        string $converter,
        string $reason,
    ): self {
        return new self(sprintf(
            'Converter "%s" for property "%s" in entity "%s" cannot be built because %s',
            $converter,
            $property,
            $entity,
            $reason,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'converter' => $converter,
            'reason' => $reason,
        ]);
    }

    /**
     * @param class-string $entity
     */
    public static function invalidConverterFactory(string $entity, string $property, string $returned): self
    {
        return new self(sprintf(
            'Converter factory for property "%s" in entity "%s" answered "%s", expected a converter',
            $property,
            $entity,
            $returned,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'returned' => $returned,
        ]);
    }

    /**
     * @param list<string> $columns
     */
    public static function propertyIsComposite(string $property, array $columns): self
    {
        return new self(sprintf(
            'Property "%s" maps to more than one column (%s) and cannot be used as a single column here',
            $property,
            implode(', ', $columns),
        ))->addContext([
            'property' => $property,
            'columns' => $columns,
        ]);
    }

    public static function propertyIsNotComposite(string $property): self
    {
        return new self(sprintf('Property "%s" maps to a single column', $property))->addContext([
            'property' => $property,
        ]);
    }

    /**
     * @param class-string $entity
     * @param list<string> $parts
     * @param list<string> $given
     */
    public static function columnsDoNotMatchParts(string $entity, string $property, array $parts, array $given): self
    {
        return new self(sprintf(
            'Columns named for property "%s" in entity "%s" are %s, expected one for each of %s',
            $property,
            $entity,
            $given === [] ? 'empty' : implode(', ', $given),
            implode(', ', $parts),
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'parts' => $parts,
            'given' => $given,
        ]);
    }

    /**
     * @param class-string $entity
     */
    public static function compositeConverterNotAllowed(string $entity, string $property, string $because): self
    {
        return new self(sprintf(
            'Property "%s" in entity "%s" cannot map to more than one column because %s',
            $property,
            $entity,
            $because,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'because' => $because,
        ]);
    }

    /**
     * @param class-string $entity
     * @param list<string> $parts
     */
    public static function invalidConverterParts(string $entity, string $property, array $parts): self
    {
        return new self(sprintf(
            'Converter for property "%s" in entity "%s" names the columns %s, which are not distinct and non-empty',
            $property,
            $entity,
            $parts === [] ? '(none)' : implode(', ', $parts),
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'parts' => $parts,
        ]);
    }

    /**
     * @param class-string $entity
     */
    public static function unknownProperty(string $entity, string $property): self
    {
        return new self(message: sprintf('Unknown property "%s" in entity "%s"', $property, $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function multipleRelations(string $entity, string $property): self
    {
        return new self(message: sprintf(
            'Property "%s" in entity "%s" has multiple relations',
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }

    public static function compositeIdentifierNotSupportedForRelation(string $entity, ?string $relation = null): self
    {
        return new self(message: sprintf(
            'Composite identifier not supported for relation %s in entity "%s"',
            $relation ?? '',
            $entity,
        ))->addContext([
            'entity' => $entity,
            'relation' => $relation,
        ]);
    }

    public static function invalidRelationType(string $entity, string $property, string $type): self
    {
        return new self(message: sprintf(
            'Invalid relation type "%s" for property "%s" in entity "%s"',
            $type,
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'type' => $type,
        ]);
    }

    public static function invalidRelationTarget(
        string $entity,
        string $property,
        string $propertyType,
        string $target,
    ): self {
        return new self(message: sprintf(
            'Invalid relation target "%s" for property "%s" in entity "%s"',
            $target,
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'propertyType' => $propertyType,
            'target' => $target,
        ]);
    }

    public static function unknownRelation(string $entity, string $relation): self
    {
        return new self(message: sprintf('Unknown relation "%s" in entity "%s"', $relation, $entity))->addContext([
            'entity' => $entity,
            'relation' => $relation,
        ]);
    }
}
