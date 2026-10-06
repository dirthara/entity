<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Query;

use Dirthara\Entity\EntityStore;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Relation\Relations;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Relation\RelationTree;
use Dirthara\Entity\Tests\Entities\Review;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Relation\RelationState;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Attribute\BelongsToMany;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Relation\Read\RelatedRows;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Relation\Read\HasOneReader;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Relation\Read\HasManyReader;
use Dirthara\Entity\Relation\RelationCollection;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Relation\DefaultRelationLoader;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Relation\Read\BelongsToOneReader;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Relation\Handle\RelationRefresher;
use Dirthara\Entity\Relation\Read\BelongsToManyReader;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Entity\Tests\Doubles\RecordingRelationLoader;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

use function sprintf;

#[CoversClass(MappingException::class)]
#[CoversClass(RelationLoadingException::class)]
#[CoversClass(EntityQuery::class)]
#[UsesClass(BelongsToMany::class)]
#[UsesClass(BelongsToOne::class)]
#[UsesClass(Entity::class)]
#[UsesClass(HasMany::class)]
#[UsesClass(HasOne::class)]
#[UsesClass(Id::class)]
#[UsesClass(EntityStore::class)]
#[UsesClass(ReflectionHydrator::class)]
#[UsesClass(BelongsToManyMetadata::class)]
#[UsesClass(BelongsToOneMetadata::class)]
#[UsesClass(EntityMetadata::class)]
#[UsesClass(HasManyMetadata::class)]
#[UsesClass(HasOneMetadata::class)]
#[UsesClass(IdentifierMetadata::class)]
#[UsesClass(MetadataFactory::class)]
#[UsesClass(MetadataRegistry::class)]
#[UsesClass(PropertyMetadata::class)]
#[UsesClass(RelationMetadata::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(ReflectionPersister::class)]
#[UsesClass(DefaultRelationHandleFactory::class)]
#[UsesClass(DefaultRelationLoader::class)]
#[UsesClass(RelationRefresher::class)]
#[UsesClass(BelongsToManyReader::class)]
#[UsesClass(BelongsToOneReader::class)]
#[UsesClass(HasManyReader::class)]
#[UsesClass(HasOneReader::class)]
#[UsesClass(RelatedRows::class)]
#[UsesClass(RelationCollection::class)]
#[UsesClass(RelationState::class)]
#[UsesClass(RelationStateRegistry::class)]
#[UsesClass(RelationTree::class)]
#[UsesClass(Relations::class)]
#[UsesClass(BooleanConverter::class)]
#[UsesClass(DateTimeConverter::class)]
#[UsesClass(FloatConverter::class)]
#[UsesClass(IntegerConverter::class)]
#[UsesClass(JsonArrayConverter::class)]
#[UsesClass(SerializedArrayConverter::class)]
#[UsesClass(StringConverter::class)]
#[UsesClass(TypeRegistry::class)]
final class EntityQueryRelationTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
    }

    #[Test]
    public function it_loads_every_row_of_a_page_in_one_batch(): void
    {
        $loader = new RecordingRelationLoader();

        $this->query($loader)->with('writer')->get();

        self::assertSame([3], $loader->batches);
        self::assertSame([['writer']], $loader->relations);
    }

    #[Test]
    public function it_asks_for_nothing_when_the_page_is_empty(): void
    {
        $loader = new RecordingRelationLoader();

        $this->connection->execute('DELETE FROM books');

        $this->query($loader)->with('writer')->get();

        self::assertSame([], $loader->batches);
    }

    #[Test]
    public function it_asks_for_nothing_when_no_relation_is_wanted(): void
    {
        $loader = new RecordingRelationLoader();

        $this->query($loader)->get();

        self::assertSame([], $loader->batches);
    }

    #[Test]
    public function it_still_calls_the_loader_for_an_entity_that_names_no_relation_but_has_an_eager_one(): void
    {
        $loader = new RecordingRelationLoader();

        $this->connection->execute("INSERT INTO reviews (body, book_id) VALUES ('Fine', 1)");

        $this->query($loader, Review::class, 'reviews')->get();

        self::assertSame([1], $loader->batches);
        self::assertSame([[]], $loader->relations);
    }

    #[Test]
    public function it_asks_for_an_eager_relation_once_even_when_it_was_named_too(): void
    {
        $loader = new RecordingRelationLoader();

        $this->connection->execute("INSERT INTO reviews (body, book_id) VALUES ('Fine', 1)");

        $this->query($loader, Review::class, 'reviews')->with('book')->get();

        self::assertSame([['book']], $loader->relations);
    }

    #[Test]
    public function it_loads_the_relations_of_a_single_row(): void
    {
        $book = self::entity(Book::class, $this->store(Book::class)->query()->with('chapters')->first());

        self::assertSame(['One', 'Two'], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_answers_nothing_for_a_first_that_matches_no_row(): void
    {
        self::assertNull($this->store(Book::class)->query()->with('chapters')->where('title', '=', 'None')->first());
    }

    #[Test]
    public function it_refuses_to_stream_rows_with_a_relation_it_was_asked_for(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Relations "writer" of entity "%s" cannot be loaded from a cursor',
            Book::class,
        ));

        $this->store(Book::class)->query()->with('writer')->cursor();
    }

    #[Test]
    public function it_refuses_to_stream_rows_of_an_entity_that_loads_a_relation_eagerly(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Relations "book" of entity "%s" cannot be loaded from a cursor',
            Review::class,
        ));

        $this->store(Review::class)->query()->cursor();
    }

    #[Test]
    public function it_names_every_relation_a_cursor_will_not_load(): void
    {
        try {
            $this->store(Book::class)->query()->with('writer', 'chapters')->cursor();

            self::fail('Expected the cursor to be refused.');
        } catch (RelationLoadingException $exception) {
            self::assertSame(['writer', 'chapters'], $exception->context['relations']);
        }
    }

    #[Test]
    public function it_streams_rows_when_no_relation_is_wanted(): void
    {
        $titles = [];

        foreach ($this->store(Book::class)->query()->cursor() as $row) {
            $titles[] = self::entity(Book::class, $row)->title;
        }

        self::assertSame(['Earthsea', 'Discworld', 'Lathe'], $titles);
    }

    #[Test]
    public function it_still_captures_the_foreign_keys_of_the_rows_it_streams(): void
    {
        $store = $this->store(Book::class);
        $rows = [];

        foreach ($store->query()->cursor() as $row) {
            $rows[] = $row;
        }

        $book = self::entity(Book::class, $rows[0] ?? null);

        $store->load($book, ['writer']);

        self::assertSame('Ursula', $book->writer->name);
    }

    #[Test]
    public function it_refuses_a_relation_the_entity_does_not_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unknown relation "missing"');

        $this->store(Book::class)->query()->with('missing');
    }

    /**
     * @param class-string $entity
     *
     * @return EntityQuery<object>
     */
    private function query(
        RecordingRelationLoader $loader,
        string $entity = Book::class,
        string $table = 'books',
    ): EntityQuery {
        return new EntityQuery(
            metadata: $this->metadata($entity),
            hydrator: new ReflectionHydrator(),
            builder: $this->connected()->table($table),
            database: $this->connected(),
            relations: new Relations(
                loader: $loader,
                handles: $this->relations()->handles,
                states: $this->relationStates,
            ),
        );
    }
}
