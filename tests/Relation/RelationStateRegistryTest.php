<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Relation\RelationState;
use Dirthara\Entity\Exception\RelationLoadingException;

final class RelationStateRegistryTest extends EntityTestCase
{
    #[Test]
    public function it_captures_the_foreign_key_of_every_belongs_to_one_in_the_row(): void
    {
        $book = new Book();

        $this->relationStates->capture($this->metadata(Book::class), $book, $this->row());

        self::assertTrue($this->relationStates->hasForeignKey($book, 'writer'));
        self::assertSame(7, $this->relationStates->foreignKey($book, 'writer'));
        self::assertNull($this->relationStates->foreignKey($book, 'editor'));
    }

    #[Test]
    public function it_captures_nothing_for_a_relation_the_other_table_owns(): void
    {
        $book = new Book();

        $this->relationStates->capture($this->metadata(Book::class), $book, $this->row());

        self::assertFalse($this->relationStates->hasForeignKey($book, 'chapters'));
        self::assertFalse($this->relationStates->hasForeignKey($book, 'jacket'));
        self::assertFalse($this->relationStates->hasForeignKey($book, 'topics'));
    }

    #[Test]
    public function it_reports_a_row_without_the_foreign_key_column(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Missing foreign key column "writer_id" for relation "writer" in entity "%s"',
            Book::class,
        ));

        $this->relationStates->capture($this->metadata(Book::class), new Book(), ['id' => 1]);
    }

    #[Test]
    public function it_remembers_which_relations_have_been_loaded(): void
    {
        $book = new Book();

        self::assertFalse($this->relationStates->isLoaded($book, 'writer'));

        $this->relationStates->markLoaded($book, 'writer');

        self::assertTrue($this->relationStates->isLoaded($book, 'writer'));
        self::assertFalse($this->relationStates->isLoaded($book, 'chapters'));
    }

    #[Test]
    public function it_keeps_one_entity_state_apart_from_another(): void
    {
        $first = new Book();
        $second = new Book();

        $this->relationStates->markLoaded($first, 'writer');

        self::assertTrue($this->relationStates->isLoaded($first, 'writer'));
        self::assertFalse($this->relationStates->isLoaded($second, 'writer'));
    }

    #[Test]
    public function it_refuses_to_answer_a_foreign_key_it_never_captured(): void
    {
        $this->expectException(RelationLoadingException::class);
        $this->expectExceptionMessage(sprintf(
            'Foreign key not captured for relation "writer" in entity "%s"',
            Book::class,
        ));

        new RelationState(entity: Book::class, relation: 'writer')->foreignKey();
    }

    #[Test]
    public function it_names_the_entity_and_relation_of_a_foreign_key_it_never_captured(): void
    {
        try {
            $this->relationStates->foreignKey(new Book(), 'writer');

            self::fail('Expected the lookup to fail.');
        } catch (RelationLoadingException $exception) {
            self::assertSame(Book::class, $exception->getContext()['entity']);
            self::assertSame('writer', $exception->getContext()['relation']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return ['id' => 1, 'title' => 'Earthsea', 'writer_id' => 7, 'editor_id' => null];
    }
}
