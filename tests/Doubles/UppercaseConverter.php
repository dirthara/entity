<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\ColumnConverter;

final readonly class UppercaseConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'uppercase';
    }

    public function toDatabase(mixed $value): string
    {
        return strtoupper((string) $value);
    }

    public function fromDatabase(mixed $value): string
    {
        return strtolower((string) $value);
    }
}
