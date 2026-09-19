<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Exception\TypeConversionException;

final class StringConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('string', new StringConverter()->type());
    }

    #[Test]
    public function it_writes_a_string_unchanged(): void
    {
        self::assertSame('Ada', new StringConverter()->toDatabase('Ada'));
    }

    #[Test]
    public function it_refuses_to_write_a_value_that_is_not_a_string(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "int", expected "string"');

        new StringConverter()->toDatabase(1);
    }

    #[Test]
    public function it_casts_what_the_column_reports_to_a_string(): void
    {
        $converter = new StringConverter();

        self::assertSame('Ada', $converter->fromDatabase('Ada'));
        self::assertSame('1', $converter->fromDatabase(1));
        self::assertNull($converter->fromDatabase(null));
    }
}
