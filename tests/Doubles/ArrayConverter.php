<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\TypeConverter;

/**
 * Claims the `array` key itself, so the default that aliases `array` to `json`
 * has something to yield to.
 */
final readonly class ArrayConverter implements TypeConverter
{
    public function type(): string
    {
        return 'array';
    }

    public function toDatabase(mixed $value): string
    {
        return implode(',', array_map(strval(...), (array) $value));
    }

    public function fromDatabase(mixed $value): array
    {
        return explode(',', (string) $value);
    }
}
