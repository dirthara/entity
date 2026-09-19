<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\TypeConverter;

/**
 * Closure factories, reached from an attribute with first-class callable
 * syntax. A literal closure is not a constant expression, so it cannot be
 * written in an attribute at all.
 */
final readonly class Converters
{
    public static function uppercase(): TypeConverter
    {
        return new UppercaseConverter();
    }

    public static function configured(): TypeConverter
    {
        return new ConfiguredConverter(prefix: 'p-');
    }

    public static function nothing(): string
    {
        return 'not a converter';
    }
}
