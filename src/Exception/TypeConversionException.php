<?php

declare(strict_types=1);

namespace Dirthara\Entity\Exception;

use Throwable;
use RuntimeException;

use function sprintf;
use function get_debug_type;

final class TypeConversionException extends RuntimeException implements EntityException
{
    use HasExceptionContext;

    public static function unsupportedType(string $type): self
    {
        return new self(sprintf('Unsupported type "%s"', $type))->addContext([
            'type' => $type,
        ]);
    }

    public static function invalidConverterType(string $expected, string $actual): self
    {
        return new self(sprintf('Invalid converter type "%s", expected "%s"', $actual, $expected))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function invalidValue(string $expected, mixed $actual): self
    {
        return new self(sprintf(
            'Invalid value of type "%s", expected "%s"',
            get_debug_type($actual),
            $expected,
        ))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function invalidColumnValue(string $expected, mixed $actual): self
    {
        return new self(sprintf(
            'Invalid column value of type "%s", expected "%s"',
            get_debug_type($actual),
            $expected,
        ))->addContext([
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public static function fractionalSecondsNotKept(string $type): self
    {
        return new self(sprintf('Type "%s" keeps no fractional seconds', $type))->addContext([
            'type' => $type,
        ]);
    }

    public static function invalidFractionalSeconds(string $type, int $fractionalSeconds, int $maximum): self
    {
        return new self(sprintf(
            'Type "%s" cannot keep %d digits of fractional seconds, expected 0 to %d',
            $type,
            $fractionalSeconds,
            $maximum,
        ))->addContext([
            'type' => $type,
            'fractionalSeconds' => $fractionalSeconds,
            'maximum' => $maximum,
        ]);
    }

    public static function conversionFailed(string $type, mixed $value, Throwable $previous): self
    {
        return new self(
            sprintf('Conversion failed for type "%s" with a value of type "%s"', $type, get_debug_type($value)),
            previous: $previous,
        )->addContext([
            'type' => $type,
            'value' => $value,
        ]);
    }
}
