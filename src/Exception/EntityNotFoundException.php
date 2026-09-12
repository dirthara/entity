<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;

use function sprintf;

class EntityNotFoundException extends EntityException
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     */
    public static function forIdentifier(mixed $identifier, string $entity, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Entity of type %s not found for identifier "%s"', $entity, $identifier),
            previous: $previous,
        )->addContext([
            'type' => $entity,
            'identifier' => $identifier,
        ]);
    }
}
