<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exceptions;

use Throwable;

class HydrationException extends EntityException
{
    public static function missingColumn(string $entity, string $property, string $column): self
    {
        return new self(sprintf(
            'Missing column "%s" for property "%s" in entity "%s"',
            $column,
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
            'column' => $column,
        ]);
    }

    public static function propertyFailed(string $entity, string $property, Throwable $previous): self
    {
        return new self(
            message: sprintf('Failed to hydrate property "%s" in entity "%s"', $property, $entity),
            previous: $previous,
        )->addContext([
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

    public static function invalidEntity(string $expected, string $actual): self
    {
        return new self(sprintf('Invalid entity type "%s", expected "%s"', $actual, $expected))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }
}
