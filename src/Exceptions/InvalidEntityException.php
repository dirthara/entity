<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exceptions;

class InvalidEntityException extends EntityException
{
    public static function forEntitySet(string $expected, string $actual): self
    {
        return new self(sprintf('Invalid entity set, expected "%s", got "%s"', $expected, $actual))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }
}
