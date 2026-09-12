<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Exceptions\TypeConversionException;

final readonly class FloatConverter implements TypeConverter
{
    public function type(): string
    {
        return 'float';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            return (float) $value;
        }

        throw TypeConversionException::invalidValue('float', $value);
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            return (float) $value;
        }

        throw TypeConversionException::invalidColumnValue('float', $value);
    }
}
