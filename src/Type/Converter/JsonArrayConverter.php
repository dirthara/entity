<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use JsonException;
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class JsonArrayConverter implements TypeConverter
{
    public function type(): string
    {
        return 'json';
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): string
    {
        if (!is_array($value)) {
            throw TypeConversionException::invalidValue(expected: 'array', actual: $value);
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TypeConversionException::conversionFailed(type: 'json', value: $value, previous: $exception);
        }
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): array
    {
        if (!is_string($value)) {
            throw TypeConversionException::invalidColumnValue(expected: 'JSON string', actual: $value);
        }

        try {
            $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TypeConversionException::conversionFailed(type: 'json', value: $value, previous: $exception);
        }

        if (!is_array($decoded)) {
            throw TypeConversionException::invalidColumnValue(expected: 'JSON array or object', actual: $decoded);
        }

        return $decoded;
    }
}
