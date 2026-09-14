<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final class IntegerConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('int', new IntegerConverter()->type());
    }

    #[Test]
    public function it_writes_an_integer_unchanged(): void
    {
        self::assertSame(42, new IntegerConverter()->toDatabase(42));
    }

    #[Test]
    public function it_refuses_to_write_a_numeric_string(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected "integer"');

        new IntegerConverter()->toDatabase('42');
    }

    #[Test]
    public function it_reads_an_integer_a_driver_reports_as_a_string(): void
    {
        $converter = new IntegerConverter();

        self::assertSame(42, $converter->fromDatabase(42));
        self::assertSame(42, $converter->fromDatabase('42'));
    }

    #[Test]
    public function it_refuses_a_column_value_that_is_not_an_integer(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "string", expected "int"');

        new IntegerConverter()->fromDatabase('4.2');
    }
}
