<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exceptions;

class InvalidIdentifierException extends EntityException
{
    public static function compositeExpected(string $entity): self
    {
        return new self(sprintf('Composite identifier expected for entity "%s"', $entity))->addContext([
            'entity' => $entity,
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
