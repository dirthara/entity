<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Persistence;

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
use Dirthara\Entity\Tests\Entities\Topic;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Relation\RelationTree;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Relation\RelationState;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Attribute\BelongsToMany;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use Dirthara\Entity\Relation\RelationLoading;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Relation\Read\RelatedRows;
use Dirthara\Entity\Relation\Read\HasOneReader;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Relation\Read\HasManyReader;
use Dirthara\Entity\Relation\RelationCollection;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Entity\Exception\PersistenceException;
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
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;

use function sprintf;

#[CoversClass(EntityStore::class)]
#[CoversClass(PersistenceException::class)]
#[CoversClass(ReflectionPersister::class)]
#[UsesClass(BelongsToMany::class)]
#[UsesClass(BelongsToOne::class)]
#[UsesClass(Entity::class)]
#[UsesClass(HasMany::class)]
#[UsesClass(HasOne::class)]
#[UsesClass(Id::class)]
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
#[UsesClass(EntityQuery::class)]
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
final class RelationPersistenceTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
    }

    #[Test]
    public function it_writes_the_foreign_key_of_a_belongs_to_one_on_insert(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = $this->writer(1);
        $book->editor = $this->writer(2);

        $this->store(Book::class)->insert($book);

        self::assertSame(['writer_id' => 1, 'editor_id' => 2], $this->keysOf('Tehanu'));
    }

    #[Test]
    public function it_writes_null_for_a_nullable_relation_that_is_set_to_null(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = $this->writer(1);
        $book->editor = null;

        $this->store(Book::class)->insert($book);

        self::assertSame(['writer_id' => 1, 'editor_id' => null], $this->keysOf('Tehanu'));
    }

    #[Test]
    public function it_refuses_an_insert_with_a_relation_the_entity_never_set(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = $this->writer(1);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessageIs(sprintf('Property "editor" of entity "%s" is not initialised', Book::class));

        $this->store(Book::class)->insert($book);
    }

    #[Test]
    public function it_refuses_an_insert_before_it_writes_anything(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = $this->writer(1);

        try {
            $this->store(Book::class)->insert($book);

            self::fail('Expected the insert to be refused.');
        } catch (PersistenceException) {
            self::assertSame(3, $this->store(Book::class)->count());
        }
    }

    #[Test]
    public function it_gives_the_entity_the_identifier_the_database_generated(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = $this->writer(1);
        $book->editor = null;

        $this->store(Book::class)->insert($book);

        self::assertSame(4, $book->id);
    }

    #[Test]
    public function it_moves_the_foreign_key_of_a_belongs_to_one_on_update(): void
    {
        $store = $this->store(Book::class);
        $book = $this->find('Earthsea');

        $store->load($book, ['writer', 'editor']);

        $book->writer = $this->writer(2);

        self::assertSame(1, $store->update($book));
        self::assertSame(['writer_id' => 2, 'editor_id' => 2], $this->keysOf('Earthsea'));
    }

    #[Test]
    public function it_clears_the_foreign_key_of_a_relation_that_is_set_to_null(): void
    {
        $store = $this->store(Book::class);
        $book = $this->find('Earthsea');

        $store->load($book, ['editor']);

        $book->editor = null;

        $store->update($book);

        self::assertSame(['writer_id' => 1, 'editor_id' => null], $this->keysOf('Earthsea'));
    }

    #[Test]
    public function it_leaves_the_foreign_keys_of_relations_it_never_loaded_alone(): void
    {
        $book = $this->find('Earthsea');

        $book->title = 'Earthsea';

        $this->store(Book::class)->update($book);

        self::assertSame(['writer_id' => 1, 'editor_id' => 2], $this->keysOf('Earthsea'));
    }

    #[Test]
    public function it_does_not_let_a_later_load_undo_a_relation_it_just_saved(): void
    {
        $store = $this->store(Book::class);
        $book = $this->find('Earthsea');

        $book->writer = $this->writer(2);

        $store->update($book);
        $store->load($book, ['writer']);

        self::assertSame('Terry', $book->writer->name);
    }

    #[Test]
    public function it_refuses_a_relation_that_has_not_been_saved_yet(): void
    {
        $book = $this->book('Tehanu');
        $book->writer = new Writer();
        $book->editor = null;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Relation "writer" of entity "%s" points at a "%s" that has no identifier yet',
            Book::class,
            Writer::class,
        ));

        $this->store(Book::class)->insert($book);
    }

    #[Test]
    public function it_refuses_null_for_a_relation_the_metadata_does_not_allow_it_for(): void
    {
        $book = $this->book('Tehanu');
        $book->editor = null;

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Null value not allowed for property "editor" in entity "%s"',
            Book::class,
        ));

        new ReflectionPersister($this->registry())->insert($this->connected(), $this->requiredEditor(), $book);
    }

    #[Test]
    public function it_refuses_a_relation_that_is_not_what_the_metadata_points_at(): void
    {
        $book = $this->book('Tehanu');
        $book->editor = $this->writer(1);

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Relation "editor" of entity "%s" expects a "%s", got "%s"',
            Book::class,
            Topic::class,
            Writer::class,
        ));

        new ReflectionPersister($this->registry())->insert($this->connected(), $this->editorOfAnotherKind(), $book);
    }

    /**
     * @return EntityMetadata<Book>
     */
    private function requiredEditor(): EntityMetadata
    {
        return $this->bookMetadataWith(target: Writer::class, nullable: false);
    }

    /**
     * @return EntityMetadata<Book>
     */
    private function editorOfAnotherKind(): EntityMetadata
    {
        return $this->bookMetadataWith(target: Topic::class, nullable: true);
    }

    /**
     * @param class-string $target
     *
     * @return EntityMetadata<Book>
     */
    private function bookMetadataWith(string $target, bool $nullable): EntityMetadata
    {
        $id = new PropertyMetadata(
            property: 'id',
            columns: ['id' => 'id'],
            propertyType: 'int',
            converter: new IntegerConverter(),
            nullable: false,
            identifier: true,
            generated: true,
        );

        $title = new PropertyMetadata(
            property: 'title',
            columns: ['title' => 'title'],
            propertyType: 'string',
            converter: new StringConverter(),
            nullable: false,
            identifier: false,
            generated: false,
        );

        return new EntityMetadata(
            entity: Book::class,
            table: 'books',
            identifier: new IdentifierMetadata([$id]),
            properties: ['id' => $id, 'title' => $title],
            relations: [
                'editor' => new BelongsToOneMetadata(
                    property: 'editor',
                    target: $target,
                    loading: RelationLoading::Explicit,
                    foreignKey: 'editor_id',
                    nullable: $nullable,
                ),
            ],
        );
    }

    /**
     * @return array{writer_id: mixed, editor_id: mixed}
     */
    private function keysOf(string $title): array
    {
        $row = $this->connection->execute('SELECT writer_id, editor_id FROM books WHERE title = ?', [$title])->first();

        self::assertIsArray($row);

        return ['writer_id' => $row['writer_id'], 'editor_id' => $row['editor_id']];
    }

    private function book(string $title): Book
    {
        $book = new Book();
        $book->title = $title;

        return $book;
    }

    private function find(string $title): Book
    {
        return self::entity(Book::class, $this->store(Book::class)->query()->where('title', '=', $title)->first());
    }

    private function writer(int $id): Writer
    {
        return self::entity(Writer::class, $this->store(Writer::class)->find($id));
    }
}
