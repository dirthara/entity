<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use RuntimeException;
use ReflectionException;

use function sprintf;

final class CreateEntityException extends RuntimeException implements EntityException
{
    use HasExceptionContext;

    public static function fromReflection(ReflectionException $exception, string $entity): self
    {
        return new self(
            message: sprintf(
                'Failed to create entity "%s": %s',
                self::printable($entity),
                self::printable($exception->getMessage()),
            ),
            previous: $exception,
        )->addContext([
            'entity' => $entity,
        ]);
    }
}
