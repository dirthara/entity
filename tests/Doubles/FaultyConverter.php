<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\ColumnConverter;

final readonly class FaultyConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'faulty';
    }

    public function toDatabase(mixed $value): string
    {
        return serialize($value);
    }

    public function fromDatabase(mixed $value): array
    {
        return [$value];
    }
}
