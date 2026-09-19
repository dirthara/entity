<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class StringConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'string';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw TypeConversionException::invalidValue(expected: 'string', actual: $value);
        }

        return $value;
    }

    public function fromDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
