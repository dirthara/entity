<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\TypeConverter;

abstract readonly class AbstractConverter implements TypeConverter
{
    public function type(): string
    {
        return 'abstract';
    }

    public function toDatabase(mixed $value): string
    {
        return (string) $value;
    }

    public function fromDatabase(mixed $value): string
    {
        return (string) $value;
    }
}
