<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Entity\Exception\TypeConversionException;

use function is_array;
use function is_string;
use function serialize;
use function unserialize;

final readonly class SerializedArrayConverter implements ColumnConverter
{
    public function type(): string
    {
        return 'serialized';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw TypeConversionException::invalidValue(expected: 'array', actual: $value);
        }

        return serialize($value);
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw TypeConversionException::invalidColumnValue(expected: 'serialized string', actual: $value);
        }

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
