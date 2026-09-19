<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;

use function sprintf;

final class EntityNotFoundException extends EntityException
{
    /**
     * @param class-string $entity
     */
    public static function forIdentifier(mixed $identifier, string $entity, ?Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Entity of type %s not found for identifier %s', $entity, self::describe($identifier)),
            previous: $previous,
        )->addContext([
            'type' => $entity,
            'identifier' => $identifier,
        ]);
    }

    private static function describe(mixed $identifier): string
    {
        if (is_scalar($identifier)) {
            return sprintf('"%s"', $identifier);
        }

        $encoded = json_encode($identifier);

        return $encoded === false ? get_debug_type($identifier) : $encoded;
    }
}
