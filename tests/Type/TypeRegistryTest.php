<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Type;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Tests\Entities\UnbackedRole;
use Dirthara\Entity\Tests\Doubles\ArrayConverter;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;

final class TypeRegistryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function builtIn(): iterable
    {
        yield 'string' => ['string'];
        yield 'int' => ['int'];
        yield 'float' => ['float'];
        yield 'bool' => ['bool'];
        yield 'json' => ['json'];
        yield 'serialized' => ['serialized'];
    }

    #[Test]
    #[DataProvider('builtIn')]
    public function it_registers_a_converter_for_every_built_in_type(string $type): void
    {
        self::assertTrue(new TypeRegistry()->has($type));
    }

    #[Test]
    public function it_registers_the_converters_it_is_given(): void
    {
        $registry = new TypeRegistry([new BackedEnumConverter(Role::class)]);

        self::assertTrue($registry->has(Role::class));
        self::assertSame(Role::Admin, $registry->get(Role::class)->fromDatabase('admin'));
    }

    #[Test]
    public function it_lets_a_later_converter_replace_a_built_in_one(): void
    {
        $replacement = new StringConverter();
        $registry = new TypeRegistry([$replacement]);

        self::assertSame($replacement, $registry->get('string'));
    }

    #[Test]
    public function it_reports_a_type_it_has_no_converter_for(): void
    {
        $registry = new TypeRegistry();

        self::assertFalse($registry->has(DateTimeImmutable::class));

        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage(sprintf('Unsupported type "%s"', DateTimeImmutable::class));

        $registry->get(DateTimeImmutable::class);
    }

    #[Test]
    public function it_converts_a_bare_array_with_the_json_converter(): void
    {
        $registry = new TypeRegistry();

        self::assertTrue($registry->has('array'));
        self::assertSame($registry->get('json'), $registry->get('array'));
        self::assertSame($registry->get('json'), $registry->resolve(propertyType: 'array'));
    }

    #[Test]
    public function it_lets_a_registered_converter_replace_the_array_default(): void
    {
        $replacement = new ArrayConverter();
        $registry = new TypeRegistry([$replacement]);

        self::assertSame($replacement, $registry->get('array'));
        self::assertNotSame($replacement, $registry->get('json'));
    }

    #[Test]
    public function it_follows_a_replaced_json_converter_for_a_bare_array(): void
    {
        $replacement = new JsonArrayConverter();
        $registry = new TypeRegistry([$replacement]);

        self::assertSame($replacement, $registry->get('array'));
    }

    #[Test]
    public function it_resolves_a_property_type_when_no_converter_is_named(): void
    {
        $registry = new TypeRegistry();

        self::assertSame($registry->get('int'), $registry->resolve(propertyType: 'int'));
    }

    #[Test]
    public function it_prefers_the_named_converter_over_the_property_type(): void
    {
        $registry = new TypeRegistry();

        self::assertSame($registry->get('json'), $registry->resolve(propertyType: 'array', converterType: 'json'));
    }

    #[Test]
    public function it_reports_a_named_converter_that_does_not_exist(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Unsupported type "csv"');

        new TypeRegistry()->resolve(propertyType: 'array', converterType: 'csv');
    }

    #[Test]
    public function it_converts_a_backed_enum_without_being_told_to(): void
    {
        $registry = new TypeRegistry();

        self::assertTrue($registry->has(Role::class));

        $converter = $registry->get(Role::class);

        self::assertSame(Role::class, $converter->type());
        self::assertSame(Role::Admin, $converter->fromDatabase('admin'));
        self::assertSame('admin', $converter->toDatabase(Role::Admin));
    }

    #[Test]
    public function it_builds_one_converter_per_backed_enum_and_keeps_it(): void
    {
        $registry = new TypeRegistry();

        self::assertSame($registry->get(Role::class), $registry->get(Role::class));
    }

    #[Test]
    public function it_lets_a_registered_converter_replace_the_one_it_would_build(): void
    {
        $replacement = new BackedEnumConverter(Role::class);
        $registry = new TypeRegistry([$replacement]);

        self::assertSame($replacement, $registry->get(Role::class));
    }

    #[Test]
    public function it_reports_an_enum_with_no_backing_type(): void
    {
        $registry = new TypeRegistry();

        self::assertFalse($registry->has(UnbackedRole::class));

        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage(sprintf('Unsupported type "%s"', UnbackedRole::class));

        $registry->get(UnbackedRole::class);
    }

    #[Test]
    public function it_registers_a_converter_after_construction(): void
    {
        $registry = new TypeRegistry();
        $registry->register(new BackedEnumConverter(Role::class));

        self::assertTrue($registry->has(Role::class));
    }
}
