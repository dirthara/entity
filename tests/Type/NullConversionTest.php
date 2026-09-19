<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type;

use stdClass;
use Throwable;
use PHPUnit\Framework\TestCase;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Tests\Entities\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;

final class NullConversionTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeConverter}>
     */
    public static function builtIn(): iterable
    {
        $registry = new TypeRegistry();

        foreach ([
            'string',
            'int',
            'float',
            'bool',
            'json',
            'serialized',
            'date',
            'time',
            'datetime',
            'timestamp',
        ] as $key) {
            yield $key => [$registry->get($key)];
        }

        yield 'a backed enum' => [new BackedEnumConverter(Role::class)];
    }

    #[Test]
    #[DataProvider('builtIn')]
    public function it_writes_null_as_null(TypeConverter $converter): void
    {
        self::assertNull($converter->toDatabase(null));
    }

    #[Test]
    #[DataProvider('builtIn')]
    public function it_reads_null_back_as_null(TypeConverter $converter): void
    {
        self::assertNull($converter->fromDatabase(null));
    }

    #[Test]
    #[DataProvider('builtIn')]
    public function it_still_refuses_a_value_it_cannot_convert(TypeConverter $converter): void
    {
        $refused = false;

        try {
            $converter->toDatabase(new stdClass());
        } catch (Throwable) {
            $refused = true;
        }

        self::assertTrue($refused, 'null passing through must not make everything pass through');
    }
}
