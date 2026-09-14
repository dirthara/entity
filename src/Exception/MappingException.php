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
     */
    public static function unknownProperty(string $entity, string $property): self
    {
        return new self(message: sprintf('Unknown property "%s" in entity "%s"', $property, $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }
}
