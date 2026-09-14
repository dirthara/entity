<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

use function serialize;

final class SerializedArrayConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('serialized', new SerializedArrayConverter()->type());
    }

    #[Test]
    public function it_serializes_an_array(): void
    {
        self::assertSame(serialize(['tier' => 'gold']), new SerializedArrayConverter()->toDatabase(['tier' => 'gold']));
    }

    #[Test]
    public function it_refuses_to_write_a_value_that_is_not_an_array(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected "array"');

        new SerializedArrayConverter()->toDatabase('a:0:{}');
    }

    #[Test]
    public function it_reads_a_serialized_array(): void
    {
        self::assertSame(
            ['tier' => 'gold'],
            new SerializedArrayConverter()->fromDatabase(serialize(['tier' => 'gold'])),
        );
    }

    #[Test]
    public function it_refuses_a_column_value_that_is_not_a_string(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "int", expected "serialized string"');

        new SerializedArrayConverter()->fromDatabase(1);
    }

    #[Test]
    public function it_refuses_a_column_value_that_does_not_unserialize_to_an_array(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "string", expected "serialized array"');

        new SerializedArrayConverter()->fromDatabase('not serialized');
    }

    #[Test]
    public function it_does_not_restore_objects_from_a_column(): void
    {
        $this->expectException(TypeConversionException::class);

        new SerializedArrayConverter()->fromDatabase(serialize(new SerializedArrayConverter()));
    }
}
