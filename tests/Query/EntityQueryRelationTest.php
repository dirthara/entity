<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Query;

use Dirthara\Entity\Query\EntityQuery;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Review;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Tests\Doubles\RecordingRelationLoader;

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
    public function it_loads_an_eager_relation_without_being_asked_for_it(): void
    {
        $loader = new RecordingRelationLoader();

        $this->connection->execute("INSERT INTO reviews (body, book_id) VALUES ('Fine', 1)");

        $this->query($loader, Review::class, 'reviews')->get();

        self::assertSame([['book']], $loader->relations);
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
    public function it_loads_the_relations_of_every_row_it_streams(): void
    {
        $titles = [];

        foreach ($this->store(Book::class)->query()->with('writer')->cursor() as $row) {
            $book = self::entity(Book::class, $row);

            $titles[] = sprintf('%s by %s', $book->title, $book->writer->name);
        }

        self::assertSame(['Earthsea by Ursula', 'Discworld by Terry', 'Lathe by Ursula'], $titles);
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
            relationLoader: $loader,
            database: $this->connected(),
            relationStates: $this->relationStates,
        );
    }
}
