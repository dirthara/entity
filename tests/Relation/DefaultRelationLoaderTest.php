<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Tests\Entities\Chapter;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Relation\RelationLoading;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Tests\Entities\Measurement;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Tests\Doubles\UnknownRelation;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Exception\RelationLoadingException;

/**
 * The loader answers to metadata, not to attributes, so these reach the
 * failures the metadata factory refuses to produce in the first place.
 */
final class DefaultRelationLoaderTest extends EntityTestCase
{
    #[Test]
    public function it_refuses_an_owner_whose_identifier_spans_more_than_one_property(): void
    {
        $metadata = $this->metadataWith(identifier: new IdentifierMetadata([
            $this->identifier('id'),
            $this->identifier('title'),
        ]), relation: $this->chapters());

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Composite identifier not supported for "%s"', Book::class));

        $this->load($metadata, $this->book(), 'chapters');
    }

    #[Test]
    public function it_refuses_an_owner_whose_identifier_spans_more_than_one_column(): void
    {
        $identifier = new PropertyMetadata(
            property: 'id',
            columns: ['amount' => 'id_amount', 'currency' => 'id_currency'],
            propertyType: Money::class,
            converter: new MoneyConverter(),
            nullable: false,
            identifier: true,
            generated: false,
        );

        $metadata = $this->metadataWith(identifier: new IdentifierMetadata([$identifier]), relation: $this->chapters());

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Composite identifier not supported for "%s"', Book::class));

        $this->load($metadata, $this->book(), 'chapters');
    }

    #[Test]
    public function it_refuses_an_identifier_that_is_null(): void
    {
        $celsius = new PropertyMetadata(
            property: 'celsius',
            columns: ['celsius' => 'celsius'],
            propertyType: 'int',
            converter: new IntegerConverter(),
            nullable: true,
            identifier: true,
            generated: false,
        );

        $metadata = new EntityMetadata(
            entity: Measurement::class,
            table: 'measurements',
            identifier: new IdentifierMetadata([$celsius]),
            properties: ['celsius' => $celsius],
            relations: ['chapters' => $this->chapters()],
        );

        $measurement = new Measurement();
        $measurement->celsius = null;

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Identifier "celsius" for entity "%s" is null', Measurement::class));

        $this->load($metadata, $measurement, 'chapters');
    }

    #[Test]
    public function it_reports_a_relation_property_the_entity_does_not_declare(): void
    {
        $metadata = $this->metadataWith(
            identifier: new IdentifierMetadata([$this->identifier('id')]),
            relation: new HasManyMetadata(
                property: 'nope',
                target: Chapter::class,
                loading: RelationLoading::Explicit,
                foreignKey: 'book_id',
            ),
        );

        $this->createLibrary();

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Unknown property "nope" in entity "%s"', Book::class));

        $this->load($metadata, $this->book(), 'nope');
    }

    #[Test]
    public function it_reports_a_relation_kind_it_has_no_branch_for(): void
    {
        $metadata = $this->metadataWith(
            identifier: new IdentifierMetadata([$this->identifier('id')]),
            relation: new UnknownRelation(
                property: 'chapters',
                target: Chapter::class,
                loading: RelationLoading::Explicit,
            ),
        );

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Unsupported relation "%s"', UnknownRelation::class));

        $this->load($metadata, $this->book(), 'chapters');
    }

    #[Test]
    public function it_refuses_a_belongs_to_one_whose_foreign_key_was_never_captured(): void
    {
        $metadata = $this->metadataWith(
            identifier: new IdentifierMetadata([$this->identifier('id')]),
            relation: new BelongsToOneMetadata(
                property: 'writer',
                target: Writer::class,
                loading: RelationLoading::Explicit,
                foreignKey: 'writer_id',
                nullable: false,
            ),
        );

        $this->createLibrary();

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Foreign key not captured for relation "writer" in entity "%s"',
            Book::class,
        ));

        $this->load($metadata, $this->book(), 'writer');
    }

    #[Test]
    public function it_does_nothing_without_an_entity_to_load_for(): void
    {
        $metadata = $this->metadataWith(identifier: new IdentifierMetadata([$this->identifier(
            'id',
        )]), relation: $this->chapters());

        $this->relationLoader()->load($this->connected(), $metadata, [], ['chapters']);

        self::assertFalse($this->relationStates->isLoaded($this->book(), 'chapters'));
    }

    #[Test]
    public function it_does_nothing_without_a_relation_to_load(): void
    {
        $metadata = $this->metadataWith(identifier: new IdentifierMetadata([$this->identifier(
            'id',
        )]), relation: $this->chapters());

        $book = $this->book();

        $this->relationLoader()->load($this->connected(), $metadata, [$book], []);

        self::assertFalse($this->relationStates->isLoaded($book, 'chapters'));
    }

    #[Test]
    public function it_refuses_an_entity_that_is_not_what_the_metadata_maps(): void
    {
        $metadata = $this->metadataWith(identifier: new IdentifierMetadata([$this->identifier(
            'id',
        )]), relation: $this->chapters());

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Expected entity "%s" but got "%s"', Book::class, Chapter::class));

        $this->load($metadata, new Chapter(), 'chapters');
    }

    private function load(EntityMetadata $metadata, object $entity, string $relation): void
    {
        $this->relationLoader()->load($this->connected(), $metadata, [$entity], [$relation]);
    }

    private function metadataWith(IdentifierMetadata $identifier, RelationMetadata $relation): EntityMetadata
    {
        return new EntityMetadata(
            entity: Book::class,
            table: 'books',
            identifier: $identifier,
            properties: [],
            relations: [$relation->property => $relation],
        );
    }

    private function chapters(): HasManyMetadata
    {
        return new HasManyMetadata(
            property: 'chapters',
            target: Chapter::class,
            loading: RelationLoading::Explicit,
            foreignKey: 'book_id',
        );
    }

    private function identifier(string $name): PropertyMetadata
    {
        return new PropertyMetadata(
            property: $name,
            columns: [$name => $name],
            propertyType: 'int',
            converter: new IntegerConverter(),
            nullable: false,
            identifier: true,
            generated: false,
        );
    }

    private function book(): Book
    {
        $book = new Book();
        $book->id = 1;

        return $book;
    }
}
