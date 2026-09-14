<?php

declare(strict_types=1);

namespace Dirthara\Entity\Type\Converter;

use BackedEnum;
use ValueError;
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Exception\TypeConversionException;

/**
 * @template T of BackedEnum
 */
final readonly class BackedEnumConverter implements TypeConverter
{
    /**
     * @param class-string<T> $enum
     *
     * @throws TypeConversionException
     */
    public function __construct(
        private string $enum,
    ) {
        // The declared `class-string<T of BackedEnum>` is a promise the analyzer
        // believes, so it reads this branch as dead. A class name resolved at runtime
        // keeps no such promise, which is what this guard is here for.
        // @mago-expect analysis:no-value
        if (!is_subclass_of($enum, BackedEnum::class)) {
            throw TypeConversionException::invalidConverterType(BackedEnum::class, $enum);
        }
    }

    public function type(): string
    {
        return $this->enum;
    }

    /**
     * @throws TypeConversionException
     */
    public function toDatabase(mixed $value): string|int
    {
        if (!$value instanceof $this->enum) {
            throw TypeConversionException::invalidValue(expected: $this->enum, actual: $value);
        }

        return $value->value;
    }

    /**
     * @throws TypeConversionException
     */
    public function fromDatabase(mixed $value): BackedEnum
    {
        if (!is_string($value) && !is_int($value)) {
            throw TypeConversionException::invalidColumnValue(
                expected: sprintf('backing value for %s', $this->enum),
                actual: $value,
            );
        }

        try {
            return $this->enum::from($value);
        } catch (ValueError $exception) {
            throw TypeConversionException::conversionFailed(type: $this->enum, value: $value, previous: $exception);
        }
    }
}
