<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Relation;

use ReflectionProperty;
use Dirthara\Entity\EntityStore;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Book;
use Dirthara\Entity\Tests\Entities\Plain;
use Dirthara\Entity\Tests\Entities\Plate;
use Dirthara\Entity\Tests\Entities\Topic;
use Dirthara\Entity\Tests\EntityTestCase;
use Dirthara\Entity\Tests\Entities\Jacket;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Tests\Entities\Chapter;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoading;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Relation\Handle\HasOneHandle;
use Dirthara\Entity\Relation\Handle\HasManyHandle;
use Dirthara\Entity\Tests\Doubles\UnknownRelation;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Entity\Exception\InvalidEntityException;
use Dirthara\Entity\Relation\Handle\BelongsToOneHandle;
use Dirthara\Entity\Relation\Handle\BelongsToManyHandle;
use Dirthara\Entity\Tests\Entities\Invalid\RequiredHasOne;
use Dirthara\Entity\Tests\Entities\Invalid\MissingPivotTable;
use Dirthara\Entity\Tests\Entities\Invalid\MissingHasOneTable;
use Dirthara\Entity\Tests\Entities\Invalid\MissingTargetTable;
use Dirthara\Entity\Tests\Entities\Invalid\UnknownForeignKeyColumn;

final class RelationHandleTest extends EntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLibrary();
        $this->seedLibrary();
    }

    #[Test]
    public function it_hands_back_a_handle_of_the_kind_the_relation_is(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        self::assertInstanceOf(BelongsToOneHandle::class, $store->relation($book, 'writer'));
        self::assertInstanceOf(HasOneHandle::class, $store->relation($book, 'jacket'));
        self::assertInstanceOf(HasManyHandle::class, $store->relation($book, 'chapters'));
        self::assertInstanceOf(BelongsToManyHandle::class, $store->relation($book, 'topics'));
    }

    #[Test]
    public function it_answers_the_relation_the_handle_speaks_for(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        self::assertSame('writer', $store->relation($book, 'writer')->relation()->property);
        self::assertSame('jacket', $store->relation($book, 'jacket')->relation()->property);
        self::assertSame('chapters', $store->relation($book, 'chapters')->relation()->property);
        self::assertSame('topics', $store->relation($book, 'topics')->relation()->property);
    }

    #[Test]
    public function it_associates_a_belongs_to_one(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToOne($book, 'writer')->associate($this->writer(2));

        self::assertSame(2, $this->column('books', 'writer_id', $book->id));
        self::assertSame('Terry', $book->writer->name);
    }

    #[Test]
    public function it_dissociates_a_belongs_to_one(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToOne($book, 'editor')->dissociate();

        self::assertNull($this->column('books', 'editor_id', $book->id));
        self::assertNull($book->editor);
    }

    #[Test]
    public function it_refuses_to_dissociate_a_relation_that_does_not_accept_null(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf('Relation "writer" of entity "%s" does not accept null', Book::class));

        $this->belongsToOne($this->book('Earthsea'), 'writer')->dissociate();
    }

    #[Test]
    public function it_refuses_to_associate_something_the_relation_does_not_point_at(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "writer" of entity "%s" expects a "%s", got "%s"',
            Book::class,
            Writer::class,
            Topic::class,
        ));

        $this->belongsToOne($this->book('Earthsea'), 'writer')->associate(new Topic());
    }

    #[Test]
    public function it_refuses_to_associate_something_that_has_not_been_saved(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage('points at a "' . Writer::class . '" that has no identifier yet');

        $this->belongsToOne($this->book('Earthsea'), 'writer')->associate(new Writer());
    }

    #[Test]
    public function it_refuses_to_write_a_relation_of_an_entity_that_has_no_identifier(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf('Property "id" of entity "%s" is not initialized', Book::class));

        $this->belongsToOne(new Book(), 'writer')->associate($this->writer(1));
    }

    #[Test]
    public function it_associates_a_has_one_by_writing_the_key_the_target_holds(): void
    {
        $book = $this->book('Discworld');
        $jacket = self::entity(Jacket::class, $this->store(Jacket::class)->find(2));

        $this->hasOne($book, 'jacket')->associate($jacket);

        self::assertSame($book->id, $this->column('jackets', 'book_id', 2));
        self::assertSame($jacket, $book->jacket);
    }

    #[Test]
    public function it_replaces_the_target_a_has_one_already_had(): void
    {
        $book = $this->book('Earthsea');

        $this->hasOne($book, 'plate')->associate($this->plate(2));

        self::assertNull($this->column('plates', 'book_id', 1));
        self::assertSame($book->id, $this->column('plates', 'book_id', 2));
        self::assertSame(2, $book->plate?->id);
    }

    #[Test]
    public function it_releases_the_target_it_replaces_before_claiming_the_new_one(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute('CREATE UNIQUE INDEX one_plate_per_book ON plates (book_id)');

        $this->hasOne($book, 'plate')->associate($this->plate(2));

        self::assertNull($this->column('plates', 'book_id', 1));
        self::assertSame($book->id, $this->column('plates', 'book_id', 2));
    }

    #[Test]
    public function it_puts_the_target_it_released_back_when_claiming_the_new_one_fails(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute(
            'CREATE TRIGGER refuse_plate BEFORE UPDATE ON plates FOR EACH ROW '
            . "WHEN NEW.id = 2 AND NEW.book_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'refused'); END",
        );

        try {
            $this->hasOne($book, 'plate')->associate($this->plate(2));

            self::fail('Expected the association to be refused.');
        } catch (PersistenceException) {
            self::assertSame($book->id, $this->column('plates', 'book_id', 1));
            self::assertNull($this->column('plates', 'book_id', 2));
        }
    }

    #[Test]
    public function it_leaves_a_has_one_alone_when_it_is_associated_with_what_it_already_has(): void
    {
        $book = $this->book('Earthsea');

        $this->hasOne($book, 'plate')->associate($this->plate(1));

        self::assertSame($book->id, $this->column('plates', 'book_id', 1));
        self::assertNull($this->column('plates', 'book_id', 2));
    }

    #[Test]
    public function it_refuses_to_associate_a_has_one_whose_owner_already_holds_more_than_one(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute("INSERT INTO plates (book_id, name) VALUES (1, 'extra')");

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Unexpected related rows "2" for relation "plate" on entity "%s"',
            Book::class,
        ));

        $this->hasOne($book, 'plate')->associate($this->plate(2));
    }

    #[Test]
    public function it_leaves_every_row_alone_when_a_has_one_already_holds_more_than_one(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute("INSERT INTO plates (book_id, name) VALUES (1, 'extra')");

        try {
            $this->hasOne($book, 'plate')->associate($this->plate(2));

            self::fail('Expected the association to be refused.');
        } catch (PersistenceException) {
            self::assertSame(1, $this->column('plates', 'book_id', 1));
            self::assertSame(1, $this->column('plates', 'book_id', 3));
            self::assertNull($this->column('plates', 'book_id', 2));
        }
    }

    #[Test]
    public function it_refuses_to_dissociate_a_has_one_whose_owner_holds_more_than_one(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute("INSERT INTO plates (book_id, name) VALUES (1, 'extra')");

        try {
            $this->hasOne($book, 'plate')->dissociate();

            self::fail('Expected the dissociation to be refused.');
        } catch (PersistenceException $exception) {
            self::assertStringContainsString('Unexpected related rows "2"', $exception->getMessage());
            self::assertSame(1, $this->column('plates', 'book_id', 1));
            self::assertSame(1, $this->column('plates', 'book_id', 3));
        }
    }

    #[Test]
    public function it_refuses_a_has_one_target_that_belongs_to_another_owner(): void
    {
        $other = $this->book('Discworld');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "plate" of entity "%s" cannot take a "%s" that already belongs to "1"',
            Book::class,
            Plate::class,
        ));

        $this->hasOne($other, 'plate')->associate($this->plate(1));
    }

    #[Test]
    public function it_leaves_the_owner_it_refused_to_take_from_untouched(): void
    {
        $other = $this->book('Discworld');

        try {
            $this->hasOne($other, 'plate')->associate($this->plate(1));

            self::fail('Expected the association to be refused.');
        } catch (PersistenceException) {
            self::assertSame(1, $this->column('plates', 'book_id', 1));
        }
    }

    #[Test]
    public function it_refuses_a_has_one_target_whose_row_is_gone(): void
    {
        $book = $this->book('Discworld');
        $plate = $this->plate(2);

        $this->connection->execute('DELETE FROM plates WHERE id = 2');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "plate" of entity "%s" points at a "%s" with identifier "2" that does not exist',
            Book::class,
            Plate::class,
        ));

        $this->hasOne($book, 'plate')->associate($plate);
    }

    #[Test]
    public function it_keeps_the_target_a_has_one_already_had_when_the_new_one_is_gone(): void
    {
        $book = $this->book('Earthsea');
        $plate = $this->plate(2);

        $this->connection->execute('DELETE FROM plates WHERE id = 2');

        try {
            $this->hasOne($book, 'plate')->associate($plate);

            self::fail('Expected the association to be refused.');
        } catch (PersistenceException) {
            self::assertSame(1, $this->column('plates', 'book_id', 1));
        }
    }

    #[Test]
    public function it_dissociates_a_has_one(): void
    {
        $book = $this->book('Earthsea');

        $this->hasOne($book, 'jacket')->dissociate();

        self::assertNull($this->column('jackets', 'book_id', 1));
        self::assertNull($book->jacket);
    }

    #[Test]
    public function it_refuses_to_dissociate_a_has_one_that_does_not_accept_null(): void
    {
        $owner = self::entity(RequiredHasOne::class, $this->store(RequiredHasOne::class)->query()->first());

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "jacket" of entity "%s" does not accept null',
            RequiredHasOne::class,
        ));

        $this->store(RequiredHasOne::class)->hasOne($owner, 'jacket')->dissociate();
    }

    #[Test]
    public function it_adds_to_a_has_many(): void
    {
        $book = $this->book('Lathe');

        $this->hasMany($book, 'chapters')->add($this->chapter(3));

        self::assertSame($book->id, $this->column('chapters', 'book_id', 3));
    }

    #[Test]
    public function it_removes_from_a_has_many(): void
    {
        $book = $this->book('Earthsea');

        $this->hasMany($book, 'chapters')->remove($this->chapter(1));

        self::assertNull($this->column('chapters', 'book_id', 1));
    }

    #[Test]
    public function it_removes_nothing_from_a_has_many_it_does_not_own(): void
    {
        $book = $this->book('Lathe');

        $this->hasMany($book, 'chapters')->remove($this->chapter(1));

        self::assertSame(1, $this->column('chapters', 'book_id', 1));
    }

    #[Test]
    public function it_loads_a_has_many_again_after_it_was_written(): void
    {
        $store = $this->store(Book::class);
        $book = $this->book('Earthsea');

        $store->load($book, ['chapters']);

        $this->hasMany($book, 'chapters')->add($this->chapter(3));

        $store->load($book, ['chapters']);

        self::assertSame(['One', 'Two', 'Alpha'], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_keeps_a_loaded_has_many_in_step_with_what_it_added(): void
    {
        $book = $this->book('Lathe');

        $this->store(Book::class)->load($book, ['chapters']);

        $this->hasMany($book, 'chapters')->add($this->chapter(3));

        self::assertSame(['Alpha'], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_keeps_a_loaded_has_many_in_step_with_what_it_removed(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['chapters']);

        $this->hasMany($book, 'chapters')->remove($this->chapter(1));

        self::assertSame(['Two'], self::names($book->chapters, 'heading'));
    }

    #[Test]
    public function it_does_not_load_a_has_many_it_was_never_asked_to_load(): void
    {
        $book = $this->book('Lathe');

        $this->hasMany($book, 'chapters')->add($this->chapter(3));

        self::assertFalse(new ReflectionProperty($book, 'chapters')->isInitialized($book));
    }

    #[Test]
    public function it_keeps_a_loaded_belongs_to_many_in_step_with_what_it_attached(): void
    {
        $book = $this->book('Lathe');

        $this->store(Book::class)->load($book, ['topics']);

        $this->belongsToMany($book, 'topics')->attach($this->topic(1));

        self::assertSame(['fantasy'], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_keeps_a_loaded_belongs_to_many_in_step_with_what_it_detached(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['topics']);

        $this->belongsToMany($book, 'topics')->detach($this->topic(1));

        self::assertSame(['scifi'], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_keeps_a_loaded_belongs_to_many_in_step_with_what_it_synced(): void
    {
        $book = $this->book('Earthsea');

        $this->store(Book::class)->load($book, ['topics']);

        $this->belongsToMany($book, 'topics')->sync([$this->topic(2)]);

        self::assertSame(['scifi'], self::names($book->topics, 'name'));
    }

    #[Test]
    public function it_does_not_load_a_belongs_to_many_it_was_never_asked_to_load(): void
    {
        $book = $this->book('Lathe');

        $this->belongsToMany($book, 'topics')->attach($this->topic(1));

        self::assertFalse(new ReflectionProperty($book, 'topics')->isInitialized($book));
    }

    #[Test]
    public function it_attaches_a_belongs_to_many(): void
    {
        $book = $this->book('Lathe');

        $this->belongsToMany($book, 'topics')->attach($this->topic(1));

        self::assertSame([1], $this->attached($book->id));
    }

    #[Test]
    public function it_attaches_something_already_attached_only_once(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToMany($book, 'topics')->attach($this->topic(1));

        self::assertSame([1, 2], $this->attached($book->id));
    }

    #[Test]
    public function it_detaches_a_belongs_to_many(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToMany($book, 'topics')->detach($this->topic(1));

        self::assertSame([2], $this->attached($book->id));
    }

    #[Test]
    public function it_syncs_a_belongs_to_many_to_exactly_what_it_is_given(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToMany($book, 'topics')->sync([$this->topic(2)]);

        self::assertSame([2], $this->attached($book->id));
    }

    #[Test]
    public function it_syncs_a_belongs_to_many_to_nothing(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToMany($book, 'topics')->sync([]);

        self::assertSame([], $this->attached($book->id));
    }

    #[Test]
    public function it_leaves_a_sync_that_changes_nothing_alone(): void
    {
        $book = $this->book('Earthsea');

        $this->belongsToMany($book, 'topics')->sync([$this->topic(1), $this->topic(2)]);

        self::assertSame([1, 2], $this->attached($book->id));
    }

    #[Test]
    public function it_syncs_a_belongs_to_many_onto_something_with_nothing_attached(): void
    {
        $book = $this->book('Lathe');

        $this->belongsToMany($book, 'topics')->sync([$this->topic(1), $this->topic(2)]);

        self::assertSame([1, 2], $this->attached($book->id));
    }

    #[Test]
    public function it_reports_what_the_database_refused_while_writing_a_belongs_to_one(): void
    {
        $owner = new UnknownForeignKeyColumn();
        $owner->id = 1;

        $handle = $this->store(UnknownForeignKeyColumn::class)->belongsToOne($owner, 'writer');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to write relation "writer" of entity "%s"',
            UnknownForeignKeyColumn::class,
        ));

        $handle->associate($this->writer(1));
    }

    #[Test]
    public function it_reports_what_the_database_refused_while_writing_a_has_one(): void
    {
        $owner = self::entity(MissingHasOneTable::class, $this->store(MissingHasOneTable::class)->query()->first());

        $handle = $this->store(MissingHasOneTable::class)->hasOne($owner, 'row');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to write relation "row" of entity "%s"',
            MissingHasOneTable::class,
        ));

        $handle->dissociate();
    }

    #[Test]
    public function it_reports_what_the_database_refused_while_writing_a_belongs_to_many(): void
    {
        $owner = self::entity(MissingPivotTable::class, $this->store(MissingPivotTable::class)->query()->first());

        $handle = $this->store(MissingPivotTable::class)->belongsToMany($owner, 'topics');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to write relation "topics" of entity "%s"',
            MissingPivotTable::class,
        ));

        $handle->attach($this->topic(1));
    }

    #[Test]
    public function it_leaves_the_join_table_alone_when_part_of_a_sync_fails(): void
    {
        $book = $this->book('Earthsea');

        $this->connection->execute("INSERT INTO topics (name) VALUES ('horror')");
        $this->connection->execute(
            'CREATE TRIGGER refuse_topic BEFORE INSERT ON book_topic FOR EACH ROW '
            . "WHEN NEW.topic_id = 3 BEGIN SELECT RAISE(ABORT, 'refused'); END",
        );

        try {
            $this->belongsToMany($book, 'topics')->sync([$this->topic(3)]);

            self::fail('Expected the sync to be refused.');
        } catch (PersistenceException) {
            self::assertSame([1, 2], $this->attached($book->id));
        }
    }

    #[Test]
    public function it_refuses_a_typed_accessor_for_a_relation_of_another_kind(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "topics" of entity "%s" answers a "%s", not a "%s"',
            Book::class,
            BelongsToManyHandle::class,
            HasManyHandle::class,
        ));

        $this->store(Book::class)->hasMany($this->book('Earthsea'), 'topics');
    }

    #[Test]
    public function it_refuses_a_to_one_accessor_for_the_other_kind_of_to_one(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "writer" of entity "%s" answers a "%s", not a "%s"',
            Book::class,
            BelongsToOneHandle::class,
            HasOneHandle::class,
        ));

        $this->store(Book::class)->hasOne($this->book('Earthsea'), 'writer');
    }

    #[Test]
    public function it_reports_a_relation_the_entity_does_not_map(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unknown relation "missing"');

        $this->store(Book::class)->relation($this->book('Earthsea'), 'missing');
    }

    #[Test]
    public function it_refuses_an_entity_of_another_class(): void
    {
        $this->expectException(InvalidEntityException::class);

        $this->store(Book::class)->relation(new Topic(), 'writer');
    }

    #[Test]
    public function it_reports_a_relation_kind_it_has_no_handle_for(): void
    {
        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Relation "chapters" of entity "%s" is of unsupported kind "%s"',
            Book::class,
            UnknownRelation::class,
        ));

        $this->storeWithUnknownRelation()->relation($this->book('Earthsea'), 'chapters');
    }

    #[Test]
    public function it_reports_what_the_database_refused_while_writing_a_relation(): void
    {
        $owner = self::entity(MissingTargetTable::class, $this->store(MissingTargetTable::class)->query()->first());

        $handle = $this->store(MissingTargetTable::class)->hasMany($owner, 'rows');

        $this->expectException(PersistenceException::class);
        $this->expectExceptionMessage(sprintf(
            'Failed to write relation "rows" of entity "%s"',
            MissingTargetTable::class,
        ));

        $row = new Plain();
        $row->id = 1;

        $handle->add($row);
    }

    private function belongsToOne(Book $book, string $relation): BelongsToOneHandle
    {
        return $this->store(Book::class)->belongsToOne($book, $relation);
    }

    private function hasOne(Book $book, string $relation): HasOneHandle
    {
        return $this->store(Book::class)->hasOne($book, $relation);
    }

    private function hasMany(Book $book, string $relation): HasManyHandle
    {
        return $this->store(Book::class)->hasMany($book, $relation);
    }

    private function belongsToMany(Book $book, string $relation): BelongsToManyHandle
    {
        return $this->store(Book::class)->belongsToMany($book, $relation);
    }

    /**
     * @return EntityStore<Book>
     */
    private function storeWithUnknownRelation(): EntityStore
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

        return new EntityStore(
            database: $this->connected(),
            metadata: new EntityMetadata(
                entity: Book::class,
                table: 'books',
                identifier: new IdentifierMetadata([$id]),
                properties: ['id' => $id],
                relations: [
                    'chapters' => new UnknownRelation(
                        property: 'chapters',
                        target: Chapter::class,
                        loading: RelationLoading::Explicit,
                    ),
                ],
            ),
            hydrator: new ReflectionHydrator(),
            persister: new ReflectionPersister($this->registry()),
            relations: $this->relations(),
        );
    }

    /**
     * @return list<int>
     */
    private function attached(int $book): array
    {
        $rows = $this->connection->execute('SELECT topic_id FROM book_topic WHERE book_id = ? ORDER BY topic_id', [
            $book,
        ])->all();

        $topics = [];

        foreach ($rows as $row) {
            self::assertIsInt($row['topic_id']);

            $topics[] = $row['topic_id'];
        }

        return $topics;
    }

    private function column(string $table, string $column, int $identifier): mixed
    {
        $row = $this->connection
            ->execute(sprintf('SELECT %s FROM %s WHERE id = ?', $column, $table), [$identifier])
            ->first();

        self::assertIsArray($row);

        return $row[$column];
    }

    private function book(string $title): Book
    {
        return self::entity(Book::class, $this->store(Book::class)->query()->where('title', '=', $title)->first());
    }

    private function writer(int $id): Writer
    {
        return self::entity(Writer::class, $this->store(Writer::class)->find($id));
    }

    private function chapter(int $id): Chapter
    {
        return self::entity(Chapter::class, $this->store(Chapter::class)->find($id));
    }

    private function plate(int $id): Plate
    {
        return self::entity(Plate::class, $this->store(Plate::class)->find($id));
    }

    private function topic(int $id): Topic
    {
        return self::entity(Topic::class, $this->store(Topic::class)->find($id));
    }
}
