<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Fixtures;

use RuntimeException;
use Dirthara\Entity\Exception\EntityException;
use Dirthara\Entity\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements EntityException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
