<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class SerializedArrayConverter implements TypeConverter
{
    public function type(): string
    {
        return 'serialized';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): string
    {
        if (!is_array($value)) {
            throw TypeConversionException::invalidValue(expected: 'array', actual: $value);
        }

        return serialize($value);
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): array
    {
        if (!is_string($value)) {
            throw TypeConversionException::invalidColumnValue(expected: 'serialized string', actual: $value);
        }

        // Malformed input warns and answers false; the check below reports it.
        // @mago-expect lint:no-error-control-operator
        $decoded = @unserialize($value, [
            'allowed_classes' => false,
        ]);

        if (!is_array($decoded)) {
            throw TypeConversionException::invalidColumnValue(expected: 'serialized array', actual: $value);
        }

        return $decoded;
    }
}
