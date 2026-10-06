<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Relation\RelationState;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Attribute\BelongsToMany;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Relation\RelationCollection;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

use function sprintf;

#[CoversClass(RelationLoadingException::class)]
#[CoversClass(RelationState::class)]
#[CoversClass(RelationStateRegistry::class)]
#[UsesClass(BelongsToMany::class)]
#[UsesClass(BelongsToOne::class)]
#[UsesClass(Entity::class)]
#[UsesClass(HasMany::class)]
#[UsesClass(HasOne::class)]
#[UsesClass(Id::class)]
#[UsesClass(BelongsToManyMetadata::class)]
#[UsesClass(BelongsToOneMetadata::class)]
#[UsesClass(EntityMetadata::class)]
#[UsesClass(HasManyMetadata::class)]
#[UsesClass(HasOneMetadata::class)]
#[UsesClass(IdentifierMetadata::class)]
#[UsesClass(MetadataFactory::class)]
#[UsesClass(PropertyMetadata::class)]
#[UsesClass(RelationMetadata::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(RelationCollection::class)]
#[UsesClass(BooleanConverter::class)]
#[UsesClass(DateTimeConverter::class)]
#[UsesClass(FloatConverter::class)]
#[UsesClass(IntegerConverter::class)]
#[UsesClass(JsonArrayConverter::class)]
#[UsesClass(SerializedArrayConverter::class)]
#[UsesClass(StringConverter::class)]
#[UsesClass(TypeRegistry::class)]
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
            self::assertSame(Book::class, $exception->context['entity']);
            self::assertSame('writer', $exception->context['relation']);
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
