<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use PDO;
use DateTime;
use DateTimeZone;
use DateTimeImmutable;
use Dirthara\Database\Database;
use PHPUnit\Framework\TestCase;
use Dirthara\Entity\EntityStore;
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Entity\Tests\Entities\Record;
use Dirthara\Entity\Metadata\MetadataFactory;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Tests\Entities\Membership;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Relation\DefaultRelationLoader;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function getenv;
use function sprintf;
use function in_array;

abstract class EntityConformanceTestCase extends TestCase
{
    protected Database $database;

    abstract protected function driverName(): DriverName;

    abstract protected function driver(): Driver;

    abstract protected function grammar(): QueryGrammar;

    abstract protected function config(): ConnectionConfig;

    abstract protected function recordsTable(): string;

    abstract protected function membershipsTable(): string;

    protected function transactions(): TransactionGrammar
    {
        return new StandardTransactionGrammar(new SavepointPrefix());
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!in_array($this->driverName()->value, PDO::getAvailableDrivers(), strict: true)) {
            self::markTestSkipped(sprintf('The %s PDO driver is not installed.', $this->driverName()->value));
        }

        $this->database = new Database(
            new ConnectionManager(new ConnectionFactory([$this->driver()]), [$this->config()], default: 'conformance'),
            new QueryGrammarResolver([$this->driverName()->value => $this->grammar()]),
        );

        $this->database->execute('DROP TABLE IF EXISTS conformance_records');
        $this->database->execute('DROP TABLE IF EXISTS memberships');
        $this->database->execute($this->recordsTable());
        $this->database->execute($this->membershipsTable());
    }

    protected function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    #[Test]
    public function it_reads_back_the_identifier_the_database_generated(): void
    {
        $records = $this->records();

        $first = $this->record('Ada');
        $second = $this->record('Grace');

        $records->insert($first);
        $records->insert($second);

        self::assertGreaterThan(0, $first->id);
        self::assertGreaterThan($first->id, $second->id);
        self::assertSame('Ada', $records->findOrFail($first->id)->displayName);
    }

    #[Test]
    public function it_round_trips_every_column_it_converts(): void
    {
        $records = $this->records();

        $record = $this->record('Ada');
        $records->insert($record);

        $found = $records->findOrFail($record->id);

        self::assertSame('Ada', $found->displayName);
        self::assertTrue($found->active);
        self::assertSame(4.5, $found->score);
        self::assertSame(['tier' => 'gold'], $found->meta);
        self::assertSame(Role::Admin, $found->role);

        self::assertSame('2026-03-04 10:15:30', $found->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('1943-12-09', $found->bornOn->format('Y-m-d'));
        self::assertSame('09:30:00', $found->opensAt->format('H:i:s'));
        self::assertSame('2026-03-04 10:15:30', $found->updatedAt->format('Y-m-d H:i:s'));

        self::assertInstanceOf(DateTimeImmutable::class, $found->createdAt);
        self::assertInstanceOf(DateTime::class, $found->updatedAt);

        self::assertSame('UTC', $found->createdAt->getTimezone()->getName());

        self::assertNull($found->note);
    }

    #[Test]
    public function it_round_trips_a_false_boolean_and_a_whole_float(): void
    {
        $records = $this->records();

        $record = $this->record('Grace', active: false, score: 5.0, role: Role::Member, note: 'Rear admiral');
        $records->insert($record);

        $found = $records->findOrFail($record->id);

        self::assertFalse($found->active);
        self::assertSame(5.0, $found->score);
        self::assertSame(Role::Member, $found->role);
        self::assertSame('Rear admiral', $found->note);
    }

    #[Test]
    public function it_updates_and_deletes_the_row_it_inserted(): void
    {
        $records = $this->records();

        $record = $this->record('Ada');
        $records->insert($record);

        $record->displayName = 'Ada Lovelace';
        $record->active = false;

        self::assertSame(1, $records->update($record));

        $found = $records->findOrFail($record->id);

        self::assertSame('Ada Lovelace', $found->displayName);
        self::assertFalse($found->active);

        self::assertSame(1, $records->delete($record));
        self::assertNull($records->find($record->id));
        self::assertSame(0, $records->count());
    }

    #[Test]
    public function it_reports_the_affected_rows_its_driver_counts_for_an_unchanged_update(): void
    {
        $records = $this->records();

        $record = $this->record('Ada');
        $records->insert($record);

        $expected = $this->driverName() === DriverName::MySql ? 0 : 1;

        self::assertSame($expected, $records->update($record));
        self::assertSame('Ada', $records->findOrFail($record->id)->displayName);
    }

    #[Test]
    public function it_queries_by_a_converted_value(): void
    {
        $records = $this->records();

        $records->insert($this->record('Ada'));
        $records->insert($this->record('Grace', active: false, role: Role::Member));

        self::assertSame(1, $records->query()->where('active', ComparisonOperator::Equal, true)->count());
        self::assertSame(1, $records->query()->where('role', ComparisonOperator::Equal, Role::Member)->count());
        self::assertTrue($records->query()->where('displayName', ComparisonOperator::Equal, 'Ada')->exists());

        $ordered = $records->query()->orderByDesc('displayName')->limit(1)->get();

        self::assertCount(1, $ordered);
        self::assertSame('Grace', $this->asRecord($ordered->values()[0])->displayName);
    }

    #[Test]
    public function it_reads_a_null_column_back_as_null(): void
    {
        $records = $this->records();

        $record = $this->record('Ada', note: null);
        $records->insert($record);

        self::assertSame(1, $records->query()->whereNull('note')->count());
        self::assertNull($this->asRecord($records->findOrFail($record->id))->note);
    }

    #[Test]
    public function it_writes_and_finds_an_entity_by_a_composite_identifier(): void
    {
        $memberships = $this->memberships();

        $membership = new Membership();
        $membership->teamId = 1;
        $membership->userId = 2;
        $membership->role = 'owner';

        $memberships->insert($membership);

        $found = $memberships->findOrFail(['teamId' => 1, 'userId' => 2]);

        self::assertSame('owner', $found->role);

        $membership->role = 'member';

        self::assertSame(1, $memberships->update($membership));
        self::assertSame('member', $memberships->findOrFail(['teamId' => 1, 'userId' => 2])->role);
        self::assertSame(1, $memberships->delete($membership));
        self::assertSame(0, $memberships->count());
    }

    /**
     * @return EntityStore<Record>
     */
    protected function records(): EntityStore
    {
        return $this->manager()->of(Record::class);
    }

    /**
     * @return EntityStore<Membership>
     */
    protected function memberships(): EntityStore
    {
        return $this->manager()->of(Membership::class);
    }

    protected function manager(): EntityManager
    {
        $types = new TypeRegistry();
        $registry = new MetadataRegistry(new MetadataFactory(new DefaultNamingStrategy(), $types));
        $states = new RelationStateRegistry();

        return new EntityManager(
            database: $this->database,
            metadata: $registry,
            hydrator: new ReflectionHydrator(),
            persister: new ReflectionPersister(),
            relationLoader: new DefaultRelationLoader(
                metadata: $registry,
                hydrator: new ReflectionHydrator(),
                states: $states,
            ),
            relationStates: $states,
            relationHandles: new DefaultRelationHandleFactory(metadata: $registry, states: $states),
        );
    }

    protected function asRecord(mixed $value): Record
    {
        if (!$value instanceof Record) {
            self::fail(sprintf('Expected a record, got %s.', get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function record(
        string $displayName,
        bool $active = true,
        float $score = 4.5,
        array $meta = ['tier' => 'gold'],
        Role $role = Role::Admin,
        ?string $note = null,
    ): Record {
        $record = new Record();
        $record->displayName = $displayName;
        $record->active = $active;
        $record->score = $score;
        $record->meta = $meta;
        $record->role = $role;
        $record->createdAt = new DateTimeImmutable('2026-03-04 10:15:30', new DateTimeZone('UTC'));
        $record->bornOn = new DateTimeImmutable('1943-12-09 00:00:00', new DateTimeZone('UTC'));
        $record->opensAt = new DateTimeImmutable('1970-01-01 09:30:00', new DateTimeZone('UTC'));
        $record->updatedAt = new DateTime('2026-03-04 10:15:30', new DateTimeZone('UTC'));
        $record->note = $note;

        return $record;
    }
}
