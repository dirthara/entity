<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exceptions;

class EntityMappingException extends EntityException
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     */
    public static function unknownProperty(string $entity, string $property): self
    {
        return new self(message: sprintf('Unknown property "%s" in entity "%s"', $property, $entity))->addContext([
            'entity' => $entity,
            'property' => $property,
        ]);
    }
}
