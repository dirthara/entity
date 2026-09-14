<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use JsonException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;

final class JsonArrayConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_type_it_converts(): void
    {
        self::assertSame('json', new JsonArrayConverter()->type());
    }

    #[Test]
    public function it_encodes_an_array(): void
    {
        self::assertSame('{"tier":"gold"}', new JsonArrayConverter()->toDatabase(['tier' => 'gold']));
    }

    #[Test]
    public function it_refuses_to_write_a_value_that_is_not_an_array(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected "array"');

        new JsonArrayConverter()->toDatabase('{}');
    }

    #[Test]
    public function it_reports_a_value_it_cannot_encode(): void
    {
        try {
            new JsonArrayConverter()->toDatabase(["\xB1\x31"]);

            self::fail('Expected the conversion to fail.');
        } catch (TypeConversionException $exception) {
            self::assertStringStartsWith('Conversion failed for type "json"', $exception->getMessage());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            self::assertSame('json', $exception->getContext()['type']);
        }
    }

    #[Test]
    public function it_decodes_an_object_and_a_list(): void
    {
        $converter = new JsonArrayConverter();

        self::assertSame(['tier' => 'gold'], $converter->fromDatabase('{"tier":"gold"}'));
        self::assertSame([1, 2], $converter->fromDatabase('[1,2]'));
    }

    #[Test]
    public function it_refuses_a_column_value_that_is_not_a_string(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "int", expected "JSON string"');

        new JsonArrayConverter()->fromDatabase(1);
    }

    #[Test]
    public function it_reports_a_column_value_it_cannot_decode(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Conversion failed for type "json"');

        new JsonArrayConverter()->fromDatabase('{');
    }

    #[Test]
    public function it_refuses_json_that_is_not_an_array_or_object(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "string", expected "JSON array or object"');

        new JsonArrayConverter()->fromDatabase('"gold"');
    }
}
