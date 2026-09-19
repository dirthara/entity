<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Metadata;

use ReflectionException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\Entities\Plain;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Ticket;
use Dirthara\Entity\Tests\Entities\Account;
use Dirthara\Entity\Tests\Entities\Article;
use Dirthara\Entity\Tests\Entities\Profile;
use Dirthara\Entity\Tests\Entities\Replica;
use Dirthara\Entity\Tests\Entities\Document;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Tests\Entities\Membership;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Tests\Entities\Invalid\Contract;
use Dirthara\Entity\Tests\Doubles\UppercaseConverter;
use Dirthara\Entity\Tests\Entities\Invalid\Behaviour;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Tests\Doubles\ConfiguredConverter;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Tests\Entities\Invalid\MixedProperty;
use Dirthara\Entity\Tests\Entities\Invalid\AbstractEntity;
use Dirthara\Entity\Tests\Entities\Invalid\IgnoredMapping;
use Dirthara\Entity\Tests\Entities\Invalid\DuplicateColumn;
use Dirthara\Entity\Tests\Entities\Invalid\UnsupportedType;
use Dirthara\Entity\Tests\Entities\Invalid\UntypedProperty;
use Dirthara\Entity\Tests\Entities\Invalid\WithoutIdentifier;
use Dirthara\Entity\Tests\Entities\Invalid\UnionTypedProperty;
use Dirthara\Entity\Tests\Entities\Invalid\ConflictingAttributes;
use Dirthara\Entity\Tests\Entities\Invalid\ConverterFactoryReturn;
use Dirthara\Entity\Tests\Entities\Invalid\UninstantiableConverter;
use Dirthara\Entity\Tests\Entities\Invalid\ConverterNeedingArguments;

final class MetadataFactoryTest extends EntityTestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function rejected(): iterable
    {
        yield 'an interface' => [Contract::class, 'interface'];
        yield 'an abstract class' => [AbstractEntity::class, 'abstract'];
        yield 'an enum' => [Role::class, 'enum'];
        yield 'a trait' => [Behaviour::class, 'trait'];
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function rejectedProperties(): iterable
    {
        yield 'no identifier' => [WithoutIdentifier::class, 'Missing identifier'];
        yield 'both Id and Column' => [ConflictingAttributes::class, 'Conflicting attributes'];
        yield 'ignored and mapped' => [IgnoredMapping::class, 'Conflicting attributes'];
        yield 'no property type' => [UntypedProperty::class, 'Missing property type for property "name"'];
        yield 'a union type' => [UnionTypedProperty::class, 'Unsupported property type "string|int"'];
        yield 'a mixed type' => [MixedProperty::class, 'Unsupported property type "mixed"'];
        yield 'two properties on one column' => [DuplicateColumn::class, 'Duplicate column "id"'];
    }

    #[Test]
    public function it_maps_an_entity_to_a_table_and_its_properties_to_columns(): void
    {
        $metadata = $this->metadata(Article::class);

        self::assertSame(Article::class, $metadata->entity);
        self::assertSame('articles', $metadata->table);
        self::assertNull($metadata->connection);
        self::assertSame(['id', 'title', 'published'], array_keys($metadata->properties));
        self::assertSame('title', $metadata->property('title')->column);
        self::assertSame('string', $metadata->property('title')->propertyType);
    }

    #[Test]
    public function it_marks_the_identifier_and_what_the_database_generates(): void
    {
        $metadata = $this->metadata(Article::class);

        self::assertTrue($metadata->identifier->isSingle());
        self::assertSame('id', $metadata->identifier->single()->property);
        self::assertTrue($metadata->property('id')->identifier);
        self::assertTrue($metadata->property('id')->generated);
        self::assertFalse($metadata->property('title')->identifier);
        self::assertFalse($metadata->property('title')->generated);
    }

    #[Test]
    public function it_collects_every_property_of_a_composite_identifier(): void
    {
        $metadata = $this->metadata(Membership::class);

        self::assertTrue($metadata->identifier->isComposite());
        self::assertSame(
            ['teamId', 'userId'],
            array_map(
                static fn(PropertyMetadata $property): string => $property->property,
                $metadata->identifier->properties,
            ),
        );
        self::assertSame('team_id', $metadata->property('teamId')->column);
    }

    #[Test]
    public function it_reads_the_table_and_connection_from_the_attribute(): void
    {
        $metadata = $this->metadata(Replica::class);

        self::assertSame('replicas', $metadata->table);
        self::assertSame('replica', $metadata->connection);
    }

    #[Test]
    public function it_maps_an_entity_without_an_entity_attribute(): void
    {
        $metadata = $this->metadata(Plain::class);

        self::assertSame('plains', $metadata->table);
        self::assertNull($metadata->connection);
        self::assertSame(['id', 'name'], array_keys($metadata->properties));
    }

    #[Test]
    public function it_honours_a_renamed_column_and_a_named_converter(): void
    {
        $metadata = $this->metadata(Profile::class);

        self::assertSame('display_name', $metadata->property('displayName')->column);
        self::assertInstanceOf(JsonArrayConverter::class, $metadata->property('meta')->converter);
        self::assertSame('array', $metadata->property('meta')->propertyType);
    }

    #[Test]
    public function it_renames_an_identifier_column(): void
    {
        $metadata = $this->metadata(Account::class);

        self::assertSame('account_uuid', $metadata->property('uuid')->column);
        self::assertSame('account_uuid', $metadata->identifier->single()->column);
        self::assertSame('label', $metadata->property('label')->column);
    }

    #[Test]
    public function it_converts_a_bare_array_property_with_json(): void
    {
        $metadata = $this->metadata(Document::class);

        self::assertSame('array', $metadata->property('payload')->propertyType);
        self::assertInstanceOf(JsonArrayConverter::class, $metadata->property('payload')->converter);
    }

    #[Test]
    public function it_builds_a_converter_named_by_class_without_registering_it(): void
    {
        $metadata = $this->metadata(Ticket::class);
        $converter = $metadata->property('code')->converter;

        self::assertInstanceOf(UppercaseConverter::class, $converter);
        self::assertSame('ABC', $converter->toDatabase('abc'));
        self::assertFalse($this->types()->has('uppercase'));
    }

    #[Test]
    public function it_asks_a_closure_for_a_converter(): void
    {
        $metadata = $this->metadata(Ticket::class);

        self::assertInstanceOf(UppercaseConverter::class, $metadata->property('label')->converter);
    }

    #[Test]
    public function it_takes_a_converter_a_closure_built_with_arguments(): void
    {
        $metadata = $this->metadata(Ticket::class);
        $converter = $metadata->property('reference')->converter;

        self::assertInstanceOf(ConfiguredConverter::class, $converter);
        self::assertSame('p-1', $converter->toDatabase('1'));
    }

    #[Test]
    public function it_gives_each_property_its_own_named_converter(): void
    {
        $metadata = $this->metadata(Ticket::class);

        self::assertNotSame($metadata->property('code')->converter, $metadata->property('label')->converter);
    }

    #[Test]
    public function it_reports_a_converter_whose_constructor_needs_arguments(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('its constructor requires arguments');

        $this->metadata(ConverterNeedingArguments::class);
    }

    #[Test]
    public function it_reports_a_converter_that_cannot_be_instantiated(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('it cannot be instantiated');

        $this->metadata(UninstantiableConverter::class);
    }

    #[Test]
    public function it_reports_a_closure_that_does_not_answer_a_converter(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('answered "string", expected a converter');

        $this->metadata(ConverterFactoryReturn::class);
    }

    #[Test]
    public function it_resolves_a_converter_from_the_property_type(): void
    {
        $metadata = $this->metadata(Profile::class);

        self::assertSame(Role::class, $metadata->property('role')->propertyType);
        self::assertSame(Role::Admin, $metadata->property('role')->converter->fromDatabase('admin'));
    }

    #[Test]
    public function it_records_which_properties_accept_null(): void
    {
        $metadata = $this->metadata(Profile::class);

        self::assertTrue($metadata->property('bio')->nullable);
        self::assertFalse($metadata->property('displayName')->nullable);
    }

    #[Test]
    public function it_maps_neither_static_nor_ignored_properties(): void
    {
        $properties = array_keys($this->metadata(Profile::class)->properties);

        self::assertNotContains('shared', $properties);
        self::assertNotContains('transient', $properties);
    }

    #[Test]
    public function it_reports_a_class_that_does_not_exist(): void
    {
        try {
            $this->metadata('Dirthara\Entity\Tests\Entities\Missing');

            self::fail('Expected the mapping to fail.');
        } catch (MappingException $exception) {
            self::assertStringContainsString('Failed to create mapping', $exception->getMessage());
            self::assertInstanceOf(ReflectionException::class, $exception->getPrevious());
        }
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_refuses_a_class_that_cannot_be_an_entity(string $entity, string $type): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Invalid entity type for "%s": %s', $entity, $type));

        $this->metadata($entity);
    }

    #[Test]
    #[DataProvider('rejectedProperties')]
    public function it_refuses_a_mapping_it_cannot_make_sense_of(string $entity, string $message): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($message);

        $this->metadata($entity);
    }

    #[Test]
    public function it_reports_a_property_type_no_converter_handles(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->expectExceptionMessage('Unsupported type "SplFileInfo"');

        $this->metadata(UnsupportedType::class);
    }
}
