<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final class BooleanConverterTest extends TestCase
{
    /**
     * Every representation the supported drivers report for a boolean column:
     * PostgreSQL answers with a bool, MySQL and SQLite with an int, and SQL Server
     * with a string.
     *
     * @return iterable<string, array{mixed, bool}>
     */
    public static function columnValues(): iterable
    {
        yield 'a native true' => [true, true];
        yield 'a native false' => [false, false];
        yield 'an integer one' => [1, true];
        yield 'an integer zero' => [0, false];
        yield 'a string one' => ['1', true];
        yield 'a string zero' => ['0', false];
    }

    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('bool', new BooleanConverter()->type());
    }

    #[Test]
    public function it_writes_a_boolean_unchanged(): void
    {
        $converter = new BooleanConverter();

        self::assertTrue($converter->toDatabase(true));
        self::assertFalse($converter->toDatabase(false));
    }

    #[Test]
    public function it_refuses_to_write_a_value_that_is_not_a_boolean(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "int", expected "bool"');

        new BooleanConverter()->toDatabase(1);
    }

    #[Test]
    #[DataProvider('columnValues')]
    public function it_reads_every_representation_a_driver_reports(mixed $value, bool $expected): void
    {
        self::assertSame($expected, new BooleanConverter()->fromDatabase($value));
    }

    #[Test]
    public function it_refuses_a_column_value_that_means_neither(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "string", expected "bool"');

        new BooleanConverter()->fromDatabase('t');
    }
}
