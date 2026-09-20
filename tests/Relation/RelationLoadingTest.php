<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\Entities\Topic;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Jacket;
use Dirthara\Entity\Tests\Entities\Review;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Tests\Entities\Anthology;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\InvalidEntityException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Tests\Entities\Invalid\RequiredHasOne;
use Dirthara\Entity\Tests\Entities\Invalid\MissingPivotColumn;
use Dirthara\Entity\Tests\Entities\Invalid\MissingTargetTable;
use Dirthara\Entity\Tests\Entities\Invalid\UncapturedForeignKey;

final class RelationLoadingTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
    }

    #[Test]
    public function it_loads_a_belongs_to_one_from_the_foreign_key_the_row_carried(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['writer']);

        self::assertSame('Ursula', self::entity(Writer::class, $book->writer)->name);
    }

    #[Test]
    public function it_leaves_a_nullable_belongs_to_one_null_when_the_foreign_key_is_null(): void
    {
        $book = $this->book('Discworld');

        $this->store(Book::class)->load($book, ['editor']);

        self::assertNull($book->editor);
    }

    #[Test]
    public function it_loads_a_has_one_from_the_key_the_target_holds(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['jacket']);

        self::assertSame('blue', self::entity(Jacket::class, $book->jacket)->colour);
    }

    #[Test]
    public function it_leaves_a_nullable_has_one_null_when_the_target_has_no_row(): void
    {
        $book = $this->book('Discworld');

        $this->store(Book::class)->load($book, ['jacket']);

        self::assertNull($book->jacket);
    }

    #[Test]
    public function it_loads_a_has_many_into_a_collection(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['chapters']);

        self::assertSame(['One', 'Two'], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_answers_an_empty_collection_for_a_has_many_without_rows(): void
    {
        $book = $this->book('Lathe');

        $this->store(Book::class)->load($book, ['chapters']);

        self::assertSame([], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_loads_a_belongs_to_many_through_the_join_table(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['topics']);

        self::assertSame(['fantasy', 'scifi'], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_answers_an_empty_collection_for_a_belongs_to_many_without_rows(): void
    {
        $book = $this->book('Lathe');

        $this->store(Book::class)->load($book, ['topics']);

        self::assertSame([], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_loads_a_has_many_from_the_other_side(): void
    {
        $writer = self::entity(Writer::class, $this->store(Writer::class)->query()->first());

        $this->store(Writer::class)->load($writer, ['books']);

        self::assertSame(['Earthsea', 'Lathe'], self::names($writer->books, 'title'));
    }

    #[Test]
    public function it_gives_every_owner_of_one_foreign_key_the_same_related_entity(): void
    {
        $books = $this->store(Book::class)->query()->with('writer')->get();

        $first = self::entity(Book::class, $books->get(0));
        $third = self::entity(Book::class, $books->get(2));

        self::assertSame('Ursula', $first->writer->name);
        self::assertSame($first->writer, $third->writer);
    }

    #[Test]
    public function it_loads_a_relation_once_and_leaves_it_alone_afterwards(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        $store->load($book, ['writer']);

        $writer = $book->writer;

        $store->load($book, ['writer']);

        self::assertSame($writer, $book->writer);
    }

    #[Test]
    public function it_loads_more_than_one_relation_in_one_call(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['writer', 'chapters', 'topics']);

        self::assertSame('Ursula', $book->writer->name);
        self::assertSame(['One', 'Two'], self::names($book->chapters, 'heading'));
        self::assertSame(['fantasy', 'scifi'], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_does_nothing_without_a_relation_to_load(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book);

        self::assertFalse(new ReflectionProperty($book, 'writer')->isInitialized($book));
    }

    #[Test]
    public function it_loads_an_eager_relation_without_being_asked(): void
    {
        $this->connection->execute("INSERT INTO reviews (body, book_id) VALUES ('Fine', 1)");

        $review = self::entity(Review::class, $this->store(Review::class)->query()->first());

        self::assertSame('Earthsea', $review->book->title);
    }

    #[Test]
    public function it_refuses_an_entity_of_another_class(): void
    {
        $this->expectException(InvalidEntityException::class);

        $this->store(Book::class)->load(new Topic(), ['writer']);
    }

    #[Test]
    public function it_reports_a_relation_the_entity_does_not_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unknown relation "missing"');

        $this->store(Book::class)->load($this->book('Earthsea'), ['missing']);
    }

    #[Test]
    public function it_reports_a_belongs_to_one_whose_row_is_gone(): void
    {
        $this->connection->execute('DELETE FROM writers WHERE id = 1');

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Related entity "%s" not found for relation "writer" with identifier "1"',
            Writer::class,
        ));

        $this->store(Book::class)->load($this->book('Earthsea'), ['writer']);
    }

    #[Test]
    public function it_refuses_a_null_foreign_key_for_a_relation_that_does_not_accept_null(): void
    {
        $this->connection->execute('UPDATE books SET writer_id = NULL WHERE title = ?', ['Earthsea']);

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Null relation not allowed for relation "writer" in entity "%s"',
            Book::class,
        ));

        $this->store(Book::class)->load($this->book('Earthsea'), ['writer']);
    }

    #[Test]
    public function it_reports_more_than_one_row_answering_a_has_one(): void
    {
        $this->connection->execute("INSERT INTO jackets (book_id, colour) VALUES (1, 'red')");

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Multiple related entities found for relation "jacket" in entity "%s" with identifier "1"',
            Book::class,
        ));

        $this->store(Book::class)->load($this->book('Earthsea'), ['jacket']);
    }

    #[Test]
    public function it_reports_a_join_table_row_whose_target_is_gone(): void
    {
        $this->connection->execute('DELETE FROM topics WHERE id = 2');

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Related entity "%s" not found for relation "topics" with identifier "2"',
            Topic::class,
        ));

        $this->store(Book::class)->load($this->book('Earthsea'), ['topics']);
    }

    #[Test]
    public function it_refuses_an_entity_whose_identifier_is_not_set(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf('Identifier "id" for entity "%s" is not initialized', Book::class));

        $this->store(Book::class)->load(new Book(), ['chapters']);
    }

    #[Test]
    public function it_refuses_a_join_table_key_that_is_not_a_value_it_can_match_on(): void
    {
        $this->connection->execute('INSERT INTO book_topic (book_id, topic_id) VALUES (3, NULL)');

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage('Invalid identifier value of type "null"');

        $this->store(Book::class)->load($this->book('Lathe'), ['topics']);
    }

    #[Test]
    public function it_reports_a_foreign_key_column_the_row_does_not_carry(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage('Missing foreign key column "writer_id" for relation "writer"');

        $this->store(UncapturedForeignKey::class)->query()->first();
    }

    #[Test]
    public function it_reports_a_join_table_column_the_row_does_not_carry(): void
    {
        $owner = self::entity(MissingPivotColumn::class, $this->store(MissingPivotColumn::class)->query()->first());

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage('Missing foreign key column "nope" for relation "topics"');

        $this->store(MissingPivotColumn::class)->load($owner, ['topics']);
    }

    #[Test]
    public function it_unpacks_a_to_many_into_a_property_that_asks_for_a_plain_array(): void
    {
        $owner = self::entity(Anthology::class, $this->store(Anthology::class)->query()->first());

        $this->store(Anthology::class)->load($owner, ['chapters']);

        self::assertIsArray($owner->chapters);
        self::assertSame(['One', 'Two'], self::names($owner->chapters, 'heading'));
    }

    #[Test]
    public function it_answers_an_empty_array_for_a_plain_array_without_rows(): void
    {
        $owner = self::entity(
            Anthology::class,
            $this->store(Anthology::class)->query()->where('title', '=', 'Lathe')->first(),
        );

        $this->store(Anthology::class)->load($owner, ['chapters']);

        self::assertSame([], $owner->chapters);
    }

    #[Test]
    public function it_reports_a_has_one_the_entity_insists_on_but_the_target_has_not_got(): void
    {
        $owner = self::entity(
            RequiredHasOne::class,
            $this->store(RequiredHasOne::class)->query()->where('title', '=', 'Discworld')->first(),
        );

        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Related entity "%s" not found for relation "jacket" with identifier "2"',
            Jacket::class,
        ));

        $this->store(RequiredHasOne::class)->load($owner, ['jacket']);
    }

    #[Test]
    public function it_reports_what_the_database_refused_while_loading_a_relation(): void
    {
        $owner = self::entity(MissingTargetTable::class, $this->store(MissingTargetTable::class)->query()->first());

        $this->expectException(EntityDatabaseException::class);
        $this->expectExceptionMessage('load relation "rows"');

        $this->store(MissingTargetTable::class)->load($owner, ['rows']);
    }

    private function book(string $title): Book
    {
        return self::entity(Book::class, $this->store(Book::class)->query()->where('title', '=', $title)->first());
    }
}
