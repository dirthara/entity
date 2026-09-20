<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Folder;
use Dirthara\Entity\Tests\Entities\EagerBook;
use Dirthara\Entity\Tests\Entities\CyclicBook;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Tests\Entities\EagerReview;
use Dirthara\Entity\Tests\Entities\PlainReview;
use Dirthara\Entity\Tests\Entities\CyclicChapter;
use Dirthara\Entity\Exception\RelationLoadingException;

final class EagerRelationLoadingTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
        $this->connection->execute("INSERT INTO reviews (body, book_id) VALUES ('Fine', 1)");
    }

    #[Test]
    public function it_follows_an_eager_relation_of_an_eager_relation(): void
    {
        $review = self::entity(EagerReview::class, $this->store(EagerReview::class)->query()->first());

        self::assertSame('Earthsea', $review->book->title);
        self::assertSame('Ursula', $review->book->writer->name);
    }

    #[Test]
    public function it_follows_an_eager_relation_of_a_relation_that_was_named(): void
    {
        $reviews = $this->store(PlainReview::class)->query()->with('book')->get();

        $review = self::entity(PlainReview::class, $reviews->get(0));

        self::assertSame('Earthsea', $review->book->title);
        self::assertSame('Ursula', $review->book->writer->name);
    }

    #[Test]
    public function it_follows_an_eager_relation_of_a_relation_that_load_asked_for(): void
    {
        $store = $this->store(PlainReview::class);
        $review = self::entity(PlainReview::class, $store->query()->first());

        $store->load($review, ['book']);

        self::assertSame('Ursula', $review->book->writer->name);
    }

    #[Test]
    public function it_loads_each_eager_level_in_one_query(): void
    {
        $connection = $this->counting();

        $this->store(EagerReview::class, $connection)->query()->get();

        self::assertCount(3, $connection->selects());
    }

    #[Test]
    public function it_follows_an_eager_relation_once_and_then_stops(): void
    {
        $book = self::entity(CyclicBook::class, $this->store(CyclicBook::class)->query()->first());

        $chapter = self::entity(CyclicChapter::class, $book->chapters->get(0));

        self::assertSame(['One', 'Two'], self::names($book->chapters, 'heading'));
        $parent = self::entity(CyclicBook::class, $chapter->book);

        self::assertSame('Earthsea', $parent->title);
        self::assertFalse(new ReflectionProperty($parent, 'chapters')->isInitialized($parent));
    }

    #[Test]
    public function it_walks_a_cycle_no_further_than_one_lap(): void
    {
        $connection = $this->counting();

        $this->store(CyclicBook::class, $connection)->query()->get();

        self::assertCount(3, $connection->selects());
    }

    #[Test]
    public function it_follows_an_eager_relation_that_points_at_its_own_entity_once(): void
    {
        $folder = self::entity(Folder::class, $this->store(Folder::class)->find(3));

        $parent = self::entity(Folder::class, $folder->parent);

        self::assertSame('child', $parent->name);
        self::assertFalse(new ReflectionProperty($parent, 'parent')->isInitialized($parent));
    }

    #[Test]
    public function it_climbs_a_self_referential_relation_by_one_step_however_deep_the_row_sits(): void
    {
        $connection = $this->counting();

        $this->store(Folder::class, $connection)->query()->where('name', '=', 'grandchild')->get();

        self::assertCount(2, $connection->selects());
    }

    #[Test]
    public function it_does_not_let_an_eager_relation_carry_a_path_further_than_it_was_written(): void
    {
        $store = $this->store(Folder::class);
        $folder = self::entity(Folder::class, $store->find(3));

        $store->load($folder, ['parent.parent']);

        $root = self::entity(Folder::class, $folder->parent?->parent);

        self::assertSame('root', $root->name);
        self::assertFalse(new ReflectionProperty($root, 'parent')->isInitialized($root));
    }

    #[Test]
    public function it_walks_a_self_referential_relation_as_far_as_the_path_names_it(): void
    {
        $store = $this->store(Folder::class);
        $folder = self::entity(Folder::class, $store->find(3));

        $store->load($folder, ['parent.parent']);

        self::assertSame('child', $folder->parent?->name);
        self::assertSame('root', $folder->parent?->parent?->name);
    }

    #[Test]
    public function it_leaves_out_an_eager_relation_the_query_asked_to_do_without(): void
    {
        $books = $this->store(EagerBook::class)->query()->without('writer')->get();

        $book = self::entity(EagerBook::class, $books->get(0));

        self::assertFalse(new ReflectionProperty($book, 'writer')->isInitialized($book));
    }

    #[Test]
    public function it_leaves_out_a_nested_eager_relation_but_keeps_the_one_above_it(): void
    {
        $reviews = $this->store(EagerReview::class)->query()->without('book.writer')->get();

        $review = self::entity(EagerReview::class, $reviews->get(0));

        self::assertSame('Earthsea', $review->book->title);
        self::assertFalse(new ReflectionProperty($review->book, 'writer')->isInitialized($review->book));
    }

    #[Test]
    public function it_lets_without_overrule_a_relation_that_was_also_named(): void
    {
        $books = $this->store(EagerBook::class)->query()->with('chapters')->without('chapters')->get();

        $book = self::entity(EagerBook::class, $books->get(0));

        self::assertFalse(new ReflectionProperty($book, 'chapters')->isInitialized($book));
    }

    #[Test]
    public function it_asks_for_nothing_when_the_only_eager_relation_is_left_out(): void
    {
        $connection = $this->counting();

        $this->store(EagerReview::class, $connection)->query()->without('book')->get();

        self::assertCount(1, $connection->selects());
    }

    #[Test]
    public function it_streams_rows_once_the_eager_relation_is_left_out(): void
    {
        $bodies = [];

        foreach ($this->store(EagerReview::class)->query()->without('book')->cursor() as $row) {
            $bodies[] = self::entity(EagerReview::class, $row)->body;
        }

        self::assertSame(['Fine'], $bodies);
    }

    #[Test]
    public function it_still_refuses_to_stream_an_entity_that_loads_a_relation_eagerly(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage('cannot be loaded from a cursor');

        $this->store(EagerReview::class)->query()->cursor();
    }

    #[Test]
    public function it_leaves_out_an_eager_relation_load_asked_to_do_without(): void
    {
        $store = $this->store(PlainReview::class);
        $review = self::entity(PlainReview::class, $store->query()->first());

        $store->load($review, ['book'], without: ['book.writer']);

        self::assertSame('Earthsea', $review->book->title);
        self::assertFalse(new ReflectionProperty($review->book, 'writer')->isInitialized($review->book));
    }

    #[Test]
    public function it_asks_for_nothing_when_load_leaves_out_the_only_relation_it_was_given(): void
    {
        $connection = $this->counting();

        $store = $this->store(PlainReview::class, $connection);
        $review = self::entity(PlainReview::class, $store->query()->first());

        $connection->forget();

        $store->load($review, ['book'], without: ['book']);

        self::assertSame([], $connection->selects());
    }

    #[Test]
    public function it_reports_a_relation_load_asks_to_do_without_that_does_not_exist(): void
    {
        $store = $this->store(PlainReview::class);
        $review = self::entity(PlainReview::class, $store->query()->first());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Unknown relation "nope" in entity "%s"', PlainReview::class));

        $store->load($review, ['book'], without: ['nope']);
    }

    #[Test]
    public function it_loads_every_level_of_a_path_that_nothing_prunes(): void
    {
        $connection = $this->counting();

        $reviews = $this->store(PlainReview::class, $connection)->query()->with('book.writer.books')->get();
        $review = self::entity(PlainReview::class, $reviews->get(0));

        self::assertSame('Ursula', $review->book->writer->name);
        self::assertSame(['Earthsea', 'Lathe'], self::names($review->book->writer->books, 'title'));
        self::assertCount(4, $connection->selects());
    }

    #[Test]
    public function it_prunes_a_path_from_the_middle_of_what_was_asked_for(): void
    {
        $connection = $this->counting();

        $reviews = $this
            ->store(PlainReview::class, $connection)
            ->query()
            ->with('book.writer.books')
            ->without('book.writer')
            ->get();

        $review = self::entity(PlainReview::class, $reviews->get(0));

        self::assertSame('Earthsea', $review->book->title);
        self::assertFalse(new ReflectionProperty($review->book, 'writer')->isInitialized($review->book));
        self::assertCount(2, $connection->selects());
    }

    #[Test]
    public function it_prunes_only_the_tail_of_what_was_asked_for(): void
    {
        $connection = $this->counting();

        $reviews = $this
            ->store(PlainReview::class, $connection)
            ->query()
            ->with('book.writer.books')
            ->without('book.writer.books')
            ->get();

        $review = self::entity(PlainReview::class, $reviews->get(0));

        self::assertSame('Ursula', $review->book->writer->name);
        self::assertFalse(new ReflectionProperty($review->book->writer, 'books')->isInitialized($review->book->writer));
        self::assertCount(3, $connection->selects());
    }

    #[Test]
    public function it_prunes_a_named_path_that_an_eager_relation_would_also_have_loaded(): void
    {
        $reviews = $this->store(PlainReview::class)->query()->with('book.writer')->without('book.writer')->get();

        $review = self::entity(PlainReview::class, $reviews->get(0));

        self::assertFalse(new ReflectionProperty($review->book, 'writer')->isInitialized($review->book));
    }

    #[Test]
    public function it_reports_a_relation_without_does_not_know(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Unknown relation "nope" in entity "%s"', EagerReview::class));

        $this->store(EagerReview::class)->query()->without('nope');
    }
}
