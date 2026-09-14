<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\TypeConverter;

/**
 * Reports a value the property's type declaration will refuse, so assigning it
 * fails inside the hydrator rather than in the converter.
 */
final readonly class FaultyConverter implements TypeConverter
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
