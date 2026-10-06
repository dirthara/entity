<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

use function is_float;
use function is_string;
use function filter_var;

use const FILTER_VALIDATE_FLOAT;

final readonly class FloatConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'float';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

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
    public function fromDatabase(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            return (float) $value;
        }

        throw TypeConversionException::invalidColumnValue('float', $value);
    }
}
