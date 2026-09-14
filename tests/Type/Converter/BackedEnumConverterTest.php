<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type\Converter;

use ValueError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;

final class BackedEnumConverterTest extends TestCase
{
    #[Test]
    public function it_reports_the_enum_it_converts(): void
    {
        self::assertSame(Role::class, new BackedEnumConverter(Role::class)->type());
    }

    #[Test]
    public function it_refuses_a_class_that_is_not_a_backed_enum(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid converter type');

        // @mago-expect analysis:invalid-argument
        new BackedEnumConverter(Article::class);
    }

    #[Test]
    public function it_writes_the_backing_value(): void
    {
        self::assertSame('admin', new BackedEnumConverter(Role::class)->toDatabase(Role::Admin));
    }

    #[Test]
    public function it_refuses_to_write_a_value_of_another_type(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid value of type "string", expected');

        new BackedEnumConverter(Role::class)->toDatabase('admin');
    }

    #[Test]
    public function it_reads_a_case_from_its_backing_value(): void
    {
        self::assertSame(Role::Member, new BackedEnumConverter(Role::class)->fromDatabase('member'));
    }

    #[Test]
    public function it_refuses_a_column_value_that_cannot_back_an_enum(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Invalid column value of type "float", expected "backing value for');

        new BackedEnumConverter(Role::class)->fromDatabase(1.5);
    }

    #[Test]
    public function it_reports_a_backing_value_that_names_no_case(): void
    {
        try {
            new BackedEnumConverter(Role::class)->fromDatabase('owner');

            self::fail('Expected the conversion to fail.');
        } catch (TypeConversionException $exception) {
            self::assertStringContainsString('Conversion failed', $exception->getMessage());
            self::assertInstanceOf(ValueError::class, $exception->getPrevious());
            self::assertSame('owner', $exception->getContext()['value']);
        }
    }
}
