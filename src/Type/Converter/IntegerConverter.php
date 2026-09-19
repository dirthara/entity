<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class IntegerConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'int';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw TypeConversionException::invalidValue(expected: 'integer', actual: $value);
        }

        return $value;
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        throw TypeConversionException::invalidColumnValue(expected: 'int', actual: $value);
    }
}
