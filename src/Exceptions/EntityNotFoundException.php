<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exceptions;

use function sprintf;

class EntityNotFoundException extends EntityException
{
    /**
     * @template T of object
     *
     * @param class-string<T> $type
     */
    public static function forIdentifier(mixed $identifier, string $type): self
    {
        return new self(sprintf('Entity of type %s not found for identifier "%s"', $type, $identifier))->addContext([
            'type' => $type,
            'identifier' => $identifier,
        ]);
    }
}
