<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;
use RuntimeException;

use function sprintf;
use function is_scalar;
use function json_encode;
use function get_debug_type;

final class EntityNotFoundException extends RuntimeException implements EntityException
{
    use HasExceptionContext;

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
            return sprintf('"%s"', self::printable((string) $identifier));
        }

        $encoded = json_encode($identifier);

        return $encoded === false ? get_debug_type($identifier) : $encoded;
    }
}
