<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use RuntimeException;
use Dirthara\Database\Exception\DatabaseException;

use function sprintf;

final class EntityDatabaseException extends RuntimeException implements EntityException
{
    use HasExceptionContext;

    public static function fromDatabaseException(DatabaseException $exception, string $entity, string $operation): self
    {
        return new self(
            message: sprintf('Database operation "%s" failed for entity "%s"', $operation, $entity),
            previous: $exception,
        )->addContext([
            'entity' => $entity,
            'operation' => $operation,
        ]);
    }
}
