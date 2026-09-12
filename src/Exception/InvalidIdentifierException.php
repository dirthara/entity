<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

class InvalidIdentifierException extends EntityException
{
    public static function compositeExpected(string $entity): self
    {
        return new self(sprintf('Composite identifier expected for entity "%s"', $entity))->addContext([
            'entity' => $entity,
        ]);
    }

    public static function identifierIsComposite(array $properties): self
    {
        return new self(sprintf('The entity has a composite identifier while a single identifier is expected: %s', implode(
            ', ',
            $properties,
        )))->addContext([
            'properties' => $properties,
        ]);
    }

    public static function missingProperty(string $entity, string $property): self
    {
        return new self(sprintf(
            'Missing property "%s" in composite identifier for entity "%s"',
            $property,
            $entity,
        ))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }
}
