<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Dirthara\Entity\Metadata\PropertyMetadata;

class InvalidIdentifierException extends EntityException
{
    public static function compositeExpected(string $entity): self
    {
        return new self(sprintf('Composite identifier expected for entity "%s"', $entity))->addContext([
            'entity' => $entity,
        ]);
    }

    /**
     * @param non-empty-list<PropertyMetadata> $properties
     */
    public static function identifierIsComposite(array $properties): self
    {
        $names = array_map(static fn(PropertyMetadata $property): string => $property->property, $properties);

        return new self(sprintf('The entity has a composite identifier while a single identifier is expected: %s', implode(
            ', ',
            $names,
        )))->addContext([
            'properties' => $names,
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
