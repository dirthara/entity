<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Relation\RelationTree;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Entity\Exception\MappingException;

final class RelationTreeTest extends TestCase
{
    #[Test]
    public function it_keeps_a_flat_path_flat(): void
    {
        $tree = RelationTree::fromPaths(['writer', 'chapters']);

        self::assertSame(['writer', 'chapters'], $tree->relations());
        self::assertTrue($tree->nestedFor('writer')->isEmpty());
    }

    #[Test]
    public function it_splits_a_path_on_every_dot(): void
    {
        $tree = RelationTree::fromPaths(['writer.books.chapters']);

        self::assertSame(['writer'], $tree->relations());
        self::assertSame(['books'], $tree->nestedFor('writer')->relations());
        self::assertSame(['chapters'], $tree->nestedFor('writer')->nestedFor('books')->relations());
    }

    #[Test]
    public function it_gathers_paths_that_share_a_head(): void
    {
        $tree = RelationTree::fromPaths(['writer.books', 'writer.address']);

        self::assertSame(['writer'], $tree->relations());
        self::assertSame(['books', 'address'], $tree->nestedFor('writer')->relations());
    }

    #[Test]
    public function it_asks_for_a_relation_once_when_it_is_named_on_its_own_as_well(): void
    {
        $tree = RelationTree::fromPaths(['writer.books', 'writer']);

        self::assertSame(['writer'], $tree->relations());
        self::assertSame(['books'], $tree->nestedFor('writer')->relations());
    }

    #[Test]
    public function it_keeps_the_order_the_paths_were_given_in(): void
    {
        self::assertSame(['topics', 'writer'], RelationTree::fromPaths(['topics', 'writer.books'])->relations());
    }

    #[Test]
    public function it_answers_an_empty_tree_for_a_relation_it_does_not_hold(): void
    {
        self::assertTrue(RelationTree::fromPaths(['writer'])->nestedFor('missing')->isEmpty());
    }

    #[Test]
    public function it_says_which_relations_it_holds(): void
    {
        $tree = RelationTree::fromPaths(['writer', 'topics.tagged']);

        self::assertTrue($tree->has('writer'));
        self::assertTrue($tree->has('topics'));
        self::assertFalse($tree->has('chapters'));
    }

    #[Test]
    public function it_tells_a_leaf_apart_from_a_branch(): void
    {
        $tree = RelationTree::fromPaths(['writer', 'topics.tagged']);

        self::assertTrue($tree->nestedFor('writer')->isEmpty());
        self::assertFalse($tree->nestedFor('topics')->isEmpty());
    }

    #[Test]
    public function it_is_empty_without_a_path(): void
    {
        self::assertTrue(RelationTree::fromPaths([])->isEmpty());
        self::assertSame([], RelationTree::fromPaths([])->relations());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'an empty string' => [''];
        yield 'a leading dot' => ['.books'];
        yield 'a trailing dot' => ['writer.'];
        yield 'a double dot' => ['writer..books'];
        yield 'a lone dot' => ['.'];
        yield 'a double dot deeper in' => ['writer.books..chapters'];
    }

    #[Test]
    #[DataProvider('malformed')]
    public function it_refuses_a_path_with_an_empty_segment(string $path): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf(
            'Invalid relation path "%s": every segment between dots has to name a relation',
            $path,
        ));

        RelationTree::fromPaths([$path]);
    }

    #[Test]
    public function it_names_the_whole_path_it_refused(): void
    {
        try {
            RelationTree::fromPaths(['writer..books']);

            self::fail('Expected the path to be refused.');
        } catch (MappingException $exception) {
            self::assertSame('writer..books', $exception->getContext()['path']);
        }
    }
}
