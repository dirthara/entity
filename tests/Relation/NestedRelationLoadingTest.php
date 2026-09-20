<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\Entities\Topic;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Tests\Entities\Chapter;
use Dirthara\Entity\Tests\Entities\Anthology;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\RelationLoadingException;

final class NestedRelationLoadingTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
    }

    #[Test]
    public function it_loads_a_relation_of_a_relation(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['writer.books']);

        self::assertSame('Ursula', $book->writer->name);
        self::assertSame(['Earthsea', 'Lathe'], self::names($book->writer->books, 'title'));
    }

    #[Test]
    public function it_loads_three_levels_deep(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['writer.books.chapters']);

        $written = self::entity(Book::class, $book->writer->books->get(0));

        self::assertSame(['One', 'Two'], self::names($written->chapters, 'heading'));
    }

    #[Test]
    public function it_loads_a_relation_of_a_to_many_relation(): void
    {
        $writer = self::entity(Writer::class, $this->store(Writer::class)->find(1));

        $this->store(Writer::class)->load($writer, ['books.writer']);

        foreach ($writer->books as $book) {
            self::assertSame('Ursula', self::entity(Book::class, $book)->writer->name);
        }
    }

    #[Test]
    public function it_loads_a_nested_relation_through_a_query(): void
    {
        $books = $this->store(Book::class)->query()->with('writer.books')->get();

        self::assertSame(
            ['Earthsea', 'Lathe'],
            self::names(self::entity(Book::class, $books->get(0))->writer->books, 'title'),
        );
    }

    #[Test]
    public function it_loads_each_level_in_one_query_however_many_owners_there_are(): void
    {
        $connection = $this->counting();

        $this->store(Book::class, $connection)->query()->with('writer.books')->get();

        self::assertCount(3, $connection->selects());
    }

    #[Test]
    public function it_descends_into_a_relation_that_was_already_loaded(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        $store->load($book, ['writer']);
        $store->load($book, ['writer.books']);

        self::assertSame(['Earthsea', 'Lathe'], self::names($book->writer->books, 'title'));
    }

    #[Test]
    public function it_loads_the_nested_relation_of_a_shared_parent_once(): void
    {
        $books = $this->store(Book::class)->query()->with('writer.books')->get();

        $first = self::entity(Book::class, $books->get(0));
        $third = self::entity(Book::class, $books->get(2));

        self::assertSame($first->writer, $third->writer);
        self::assertSame($first->writer->books, $third->writer->books);
    }

    #[Test]
    public function it_descends_into_a_collection_that_was_already_loaded(): void
    {
        $store = $this->store(Writer::class);
        $writer = self::entity(Writer::class, $store->find(1));

        $store->load($writer, ['books']);
        $store->load($writer, ['books.writer']);

        foreach ($writer->books as $book) {
            self::assertSame('Ursula', self::entity(Book::class, $book)->writer->name);
        }
    }

    #[Test]
    public function it_descends_into_a_plain_array_that_was_already_loaded(): void
    {
        $store = $this->store(Anthology::class);
        $owner = self::entity(Anthology::class, $store->query()->first());

        $store->load($owner, ['chapters']);
        $store->load($owner, ['chapters.book']);

        self::assertSame('Earthsea', self::entity(Chapter::class, $owner->chapters[0])->book?->title);
    }

    #[Test]
    public function it_loads_a_relation_of_a_has_one(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['jacket.book']);

        self::assertSame('blue', $book->jacket?->colour);
        self::assertSame('Earthsea', $book->jacket?->book?->title);
    }

    #[Test]
    public function it_loads_a_has_one_and_its_relation_in_one_query_each(): void
    {
        $connection = $this->counting();

        $this->store(Book::class, $connection)->query()->with('jacket.book')->get();

        self::assertCount(3, $connection->selects());
    }

    #[Test]
    public function it_descends_into_a_has_one_that_was_already_loaded(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        $store->load($book, ['jacket']);
        $store->load($book, ['jacket.book']);

        self::assertSame('Earthsea', $book->jacket?->book?->title);
    }

    #[Test]
    public function it_stops_at_a_has_one_that_found_nothing(): void
    {
        $book = $this->book('Discworld');

        $this->store(Book::class)->load($book, ['jacket.book']);

        self::assertNull($book->jacket);
    }

    #[Test]
    public function it_loads_a_relation_of_a_belongs_to_many(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['topics.books']);

        self::assertSame(['fantasy', 'scifi'], self::names($book->topics, 'name'));

        $fantasy = self::entity(Topic::class, $book->topics->get(0));
        $scifi = self::entity(Topic::class, $book->topics->get(1));

        self::assertSame(['Earthsea', 'Discworld'], self::names($fantasy->books, 'title'));
        self::assertSame(['Earthsea'], self::names($scifi->books, 'title'));
    }

    #[Test]
    public function it_walks_a_belongs_to_many_through_its_join_table_at_every_level(): void
    {
        $connection = $this->counting();

        $this->store(Book::class, $connection)->query()->with('topics.books')->get();

        self::assertSame(
            [
                'SELECT * FROM "books"',
                'SELECT * FROM "book_topic" WHERE "book_id" IN (?, ?, ?)',
                'SELECT * FROM "topics" WHERE "id" IN (?, ?)',
                'SELECT * FROM "book_topic" WHERE "topic_id" IN (?, ?)',
                'SELECT * FROM "books" WHERE "id" IN (?, ?)',
            ],
            $connection->selects(),
        );
    }

    #[Test]
    public function it_keeps_batching_a_belongs_to_many_however_many_levels_deep_it_goes(): void
    {
        $connection = $this->counting();

        $this->store(Book::class, $connection)->query()->with('topics.books.topics')->get();

        self::assertSame(
            [
                'SELECT * FROM "books"',
                'SELECT * FROM "book_topic" WHERE "book_id" IN (?, ?, ?)',
                'SELECT * FROM "topics" WHERE "id" IN (?, ?)',
                'SELECT * FROM "book_topic" WHERE "topic_id" IN (?, ?)',
                'SELECT * FROM "books" WHERE "id" IN (?, ?)',
                'SELECT * FROM "book_topic" WHERE "book_id" IN (?, ?)',
                'SELECT * FROM "topics" WHERE "id" IN (?, ?)',
            ],
            $connection->selects(),
        );
    }

    #[Test]
    public function it_descends_into_a_belongs_to_many_that_was_already_loaded(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        $store->load($book, ['topics']);
        $store->load($book, ['topics.books']);

        self::assertSame(
            ['Earthsea', 'Discworld'],
            self::names(self::entity(Topic::class, $book->topics->get(0))->books, 'title'),
        );
    }

    #[Test]
    public function it_stops_at_a_belongs_to_many_that_is_empty(): void
    {
        $book = $this->book('Lathe');

        $this->store(Book::class)->load($book, ['topics.books']);

        self::assertSame([], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_stops_at_a_to_one_that_was_already_loaded_as_null(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Discworld');

        $store->load($book, ['editor']);
        $store->load($book, ['editor.books']);

        self::assertNull($book->editor);
    }

    #[Test]
    public function it_leaves_a_nested_relation_alone_when_the_middle_is_null(): void
    {
        $book = $this->book('Discworld');

        $this->store(Book::class)->load($book, ['editor.books']);

        self::assertNull($book->editor);
    }

    #[Test]
    public function it_still_loads_the_head_of_a_path(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['writer.books']);

        self::assertTrue(new ReflectionProperty($book, 'writer')->isInitialized($book));
    }

    #[Test]
    public function it_reports_a_loaded_to_many_that_no_longer_holds_entities(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "chapters" of entity "%s" holds a "string" where a "%s" was expected',
            Anthology::class,
            Chapter::class,
        ));

        $this->descendInto(['not an entity']);
    }

    #[Test]
    public function it_reports_a_loaded_to_many_that_holds_the_wrong_kind_of_entity(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "chapters" of entity "%s" holds a "%s" where a "%s" was expected',
            Anthology::class,
            Topic::class,
            Chapter::class,
        ));

        $this->descendInto([self::entity(Topic::class, $this->store(Topic::class)->find(1))]);
    }

    /**
     * @param list<mixed> $chapters
     */
    private function descendInto(array $chapters): void
    {
        $store = $this->store(Anthology::class);
        $owner = self::entity(Anthology::class, $store->query()->first());

        $store->load($owner, ['chapters']);

        new ReflectionProperty($owner, 'chapters')->setRawValue($owner, $chapters);

        $store->load($owner, ['chapters.book']);
    }

    #[Test]
    public function it_reports_a_nested_relation_the_target_does_not_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Unknown relation "nope" in entity "%s"', Writer::class));

        $this->store(Book::class)->query()->with('writer.nope');
    }

    #[Test]
    public function it_reports_a_nested_path_on_something_that_is_not_a_relation(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Unknown relation "title" in entity "%s"', Book::class));

        $this->store(Book::class)->query()->with('title.writer');
    }

    #[Test]
    public function it_reports_a_nested_relation_load_asks_for_that_does_not_exist(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Unknown relation "nope" in entity "%s"', Writer::class));

        $this->store(Book::class)->load($this->book('Earthsea'), ['writer.nope']);
    }

    #[Test]
    public function it_refuses_a_malformed_path_where_the_query_was_given_it(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Invalid relation path ".books"');

        $this->store(Book::class)->query()->with('.books');
    }

    #[Test]
    public function it_refuses_a_malformed_path_where_load_was_given_it(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Invalid relation path "writer."');

        $this->store(Book::class)->load($this->book('Earthsea'), ['writer.']);
    }

    #[Test]
    public function it_refuses_a_malformed_path_where_without_was_given_it(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Invalid relation path "writer..books"');

        $this->store(Book::class)->query()->without('writer..books');
    }

    #[Test]
    public function it_still_refuses_to_stream_a_nested_relation(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage('cannot be loaded from a cursor');

        $this->store(Book::class)->query()->with('writer.books')->cursor();
    }

    private function book(string $title): Book
    {
        return self::entity(Book::class, $this->store(Book::class)->query()->where('title', '=', $title)->first());
    }
}
