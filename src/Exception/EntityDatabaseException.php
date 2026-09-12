<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Dirthara\Database\Exceptions\DatabaseException;

class EntityDatabaseException extends EntityException
{
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
