<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class BooleanConverter implements TypeConverter
{
    public function type(): string
    {
        return 'bool';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw TypeConversionException::invalidValue(expected: 'bool', actual: $value);
        }

        return $value;
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => throw TypeConversionException::invalidColumnValue(expected: 'bool', actual: $value),
        };
    }
}
