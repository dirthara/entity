<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests;

use Dirthara\Database\Database;
use PHPUnit\Framework\TestCase;
use Dirthara\Entity\EntityStore;
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Tests\Entities\Role;
use Dirthara\Database\Connection\Connection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

/**
 * SQLite in memory stands in for a server wherever the behaviour under test is
 * the package's own. Behaviour that belongs to a driver lives in the conformance
 * suite in `tests/Integration`.
 */
abstract class EntityTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->sqlite();
    }

    protected function sqlite(string $database = ':memory:'): Connection
    {
        return new PdoConnection(
            new ConnectionConfig(driver: DriverName::SQLite, name: 'default', database: $database),
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix())),
        );
    }

    protected function connected(?Connection $connection = null): ConnectedDatabase
    {
        return new ConnectedDatabase(connection: $connection ?? $this->connection, grammar: new SQLiteQueryGrammar());
    }

    /**
     * A `Database` over the named connections, the first of which is the default.
     */
    protected function database(string ...$names): Database
    {
        $names = $names === [] ? ['default'] : $names;
        $configs = array_map(
            static fn(string $name): ConnectionConfig => new ConnectionConfig(
                driver: DriverName::SQLite,
                name: $name,
                database: ':memory:',
            ),
            $names,
        );

        return new Database(
            new ConnectionManager(
                new ConnectionFactory([new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))]),
                $configs,
                default: $names[0],
            ),
            new QueryGrammarResolver([DriverName::SQLite->value => new SQLiteQueryGrammar()]),
        );
    }

    protected function types(TypeConverter ...$converters): TypeRegistry
    {
        return new TypeRegistry([new BackedEnumConverter(Role::class), ...$converters]);
    }

    protected function factory(?TypeRegistry $types = null): MetadataFactory
    {
        return new MetadataFactory(new DefaultNamingStrategy(), $types ?? $this->types());
    }

    protected function registry(?TypeRegistry $types = null): MetadataRegistry
    {
        return new MetadataRegistry($this->factory($types));
    }

    /**
     * @param class-string $entity
     */
    protected function metadata(string $entity, ?TypeRegistry $types = null): EntityMetadata
    {
        return $this->factory($types)->create($entity);
    }

    /**
     * @param class-string $entity
     */
    protected function store(string $entity, ?Connection $connection = null): EntityStore
    {
        return new EntityStore(
            database: $this->connected($connection),
            metadata: $this->metadata($entity),
            hydrator: new ReflectionHydrator(),
            persister: new ReflectionPersister(),
        );
    }

    protected function manager(Database $database): EntityManager
    {
        return new EntityManager(
            database: $database,
            metadata: $this->registry(),
            hydrator: new ReflectionHydrator(),
            persister: new ReflectionPersister(),
        );
    }

    /**
     * @param non-empty-string $table
     */
    protected function createTable(string $table, ?Connection $connection = null): void
    {
        ($connection ?? $this->connection)->execute($table);
    }

    protected function createArticles(?Connection $connection = null): void
    {
        $this->createTable(
            'CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, published INTEGER NOT NULL)',
            $connection,
        );
    }

    /**
     * The same table without a primary key, so one identifier can match several rows.
     */
    protected function createAmbiguousArticles(?Connection $connection = null): void
    {
        $this->createTable(
            'CREATE TABLE articles (id INTEGER, title TEXT NOT NULL, published INTEGER NOT NULL)',
            $connection,
        );
    }

    protected function createProfiles(?Connection $connection = null): void
    {
        $this->createTable(
            'CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, display_name TEXT NOT NULL, meta TEXT NOT NULL, role TEXT NOT NULL, bio TEXT)',
            $connection,
        );
    }

    protected function createMemberships(?Connection $connection = null): void
    {
        $this->createTable(
            'CREATE TABLE memberships (team_id INTEGER NOT NULL, user_id INTEGER NOT NULL, role TEXT NOT NULL)',
            $connection,
        );
    }

    protected function createCountries(?Connection $connection = null): void
    {
        $this->createTable('CREATE TABLE countries (code TEXT PRIMARY KEY, name TEXT NOT NULL)', $connection);
    }

    protected function insertArticle(string $title, ?Connection $connection = null): void
    {
        ($connection ?? $this->connection)->execute('INSERT INTO articles (title, published) VALUES (?, 1)', [$title]);
    }

    protected function insertDraft(string $title, ?Connection $connection = null): void
    {
        ($connection ?? $this->connection)->execute('INSERT INTO articles (title, published) VALUES (?, 0)', [$title]);
    }

    /**
     * The store answers with `object` because its template parameter never binds,
     * so narrow here rather than asserting on a property of an unknown type.
     *
     * @template T of object
     *
     * @param class-string<T> $expected
     *
     * @return T
     */
    protected static function entity(string $expected, mixed $value): object
    {
        if (!$value instanceof $expected) {
            self::fail(sprintf('Expected a %s, got %s.', $expected, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(string $table, ?Connection $connection = null): array
    {
        return ($connection ?? $this->connection)->execute(sprintf('SELECT * FROM %s', $table))->all();
    }
}
