<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Type\ColumnConverter;

final readonly class Converters
{
    public static function uppercase(): ColumnConverter
    {
        return new UppercaseConverter();
    }

    public static function configured(): ColumnConverter
    {
        return new ConfiguredConverter(prefix: 'p-');
    }

    public static function nothing(): string
    {
        return 'not a converter';
    }
}
