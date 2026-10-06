<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidEntityException extends InvalidArgumentException implements EntityException
{
    use HasExceptionContext;

    public static function forEntityStore(string $expected, string $actual): self
    {
        return new self(sprintf('Invalid entity set, expected "%s", got "%s"', $expected, $actual))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }
}
