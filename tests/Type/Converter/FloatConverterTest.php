<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final class FloatConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('float', new FloatConverter()->type());
    }

    #[Test]
    public function it_writes_a_float_and_a_numeric_string(): void
    {
        $converter = new FloatConverter();

        self::assertSame(1.5, $converter->toDatabase(1.5));
        self::assertSame(1.5, $converter->toDatabase('1.5'));
    }

    #[Test]
    public function it_refuses_to_write_a_value_that_is_not_numeric(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected "float"');

        new FloatConverter()->toDatabase('one');
    }

    #[Test]
    public function it_reads_a_float_and_a_numeric_string(): void
    {
        $converter = new FloatConverter();

        self::assertSame(1.5, $converter->fromDatabase(1.5));
        self::assertSame(1.5, $converter->fromDatabase('1.5'));
    }

    #[Test]
    public function it_refuses_a_column_value_that_is_not_numeric(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "string", expected "float"');

        new FloatConverter()->fromDatabase('not a number');
    }
}
