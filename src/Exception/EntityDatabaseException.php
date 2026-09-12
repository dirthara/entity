<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Dirthara\Database\Exceptions\DatabaseException;

class EntityDatabaseException extends EntityException
{
    public static function fromDatabaseException(DatabaseException $exception): self
    {
        return new self($exception->getMessage(), $exception->getCode(), $exception);
    }
}
