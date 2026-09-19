<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class BooleanConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'bool';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (!is_bool($value)) {
            throw TypeConversionException::invalidValue(expected: 'bool', actual: $value);
        }

        return $value;
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => throw TypeConversionException::invalidColumnValue(expected: 'bool', actual: $value),
        };
    }
}
