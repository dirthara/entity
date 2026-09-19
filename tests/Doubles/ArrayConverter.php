<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\TypeConverter;

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
