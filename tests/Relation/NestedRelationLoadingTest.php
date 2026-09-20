<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
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

        $this->store(Book::class)->load($book, 'writer.books');

        self::assertSame('Ursula', $book->writer->name);
        self::assertSame(['Earthsea', 'Lathe'], self::names($book->writer->books, 'title'));
    }

    #[Test]
    public function it_loads_three_levels_deep(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, 'writer.books.chapters');

        $written = self::entity(Book::class, $book->writer->books->get(0));

        self::assertSame(['One', 'Two'], self::names($written->chapters, 'heading'));
    }

    #[Test]
    public function it_loads_a_relation_of_a_to_many_relation(): void
    {
        $writer = self::entity(Writer::class, $this->store(Writer::class)->find(1));

        $this->store(Writer::class)->load($writer, 'books.writer');

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

        $store->load($book, 'writer');
        $store->load($book, 'writer.books');

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

        $store->load($writer, 'books');
        $store->load($writer, 'books.writer');

        foreach ($writer->books as $book) {
            self::assertSame('Ursula', self::entity(Book::class, $book)->writer->name);
        }
    }

    #[Test]
    public function it_descends_into_a_plain_array_that_was_already_loaded(): void
    {
        $store = $this->store(Anthology::class);
        $owner = self::entity(Anthology::class, $store->query()->first());

        $store->load($owner, 'chapters');
        $store->load($owner, 'chapters.book');

        self::assertSame('Earthsea', self::entity(Chapter::class, $owner->chapters[0])->book?->title);
    }

    #[Test]
    public function it_leaves_a_nested_relation_alone_when_the_middle_is_null(): void
    {
        $book = $this->book('Discworld');

        $this->store(Book::class)->load($book, 'editor.books');

        self::assertNull($book->editor);
    }

    #[Test]
    public function it_still_loads_the_head_of_a_path(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, 'writer.books');

        self::assertTrue(new ReflectionProperty($book, 'writer')->isInitialized($book));
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

        $this->store(Book::class)->load($this->book('Earthsea'), 'writer.nope');
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
