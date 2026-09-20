<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests;

use ReflectionProperty;
use Dirthara\Database\Database;
use PHPUnit\Framework\TestCase;
use Dirthara\Entity\EntityStore;
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Type\ColumnConverter;
use Dirthara\Database\Connection\Connection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;
use Dirthara\Entity\Metadata\MetadataFactory;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Entity\Relation\DefaultRelationLoader;
use Dirthara\Entity\Relation\RelationHandleFactory;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

abstract class EntityTestCase extends TestCase
{
    protected Connection $connection;

    protected RelationStateRegistry $relationStates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->sqlite();
        $this->relationStates = new RelationStateRegistry();
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

    protected function types(ColumnConverter ...$converters): TypeRegistry
    {
        return new TypeRegistry($converters);
    }

    protected function factory(?TypeRegistry $types = null): MetadataFactory
    {
        return new MetadataFactory(new DefaultNamingStrategy(), $types ?? $this->types());
    }

    protected function registry(?TypeRegistry $types = null): MetadataRegistry
    {
        return new MetadataRegistry($this->factory($types));
    }

    protected function relationLoader(?MetadataRegistry $registry = null): RelationLoader
    {
        return new DefaultRelationLoader(
            metadata: $registry ?? $this->registry(),
            hydrator: new ReflectionHydrator(),
            states: $this->relationStates,
        );
    }

    protected function relationHandles(?MetadataRegistry $registry = null): RelationHandleFactory
    {
        return new DefaultRelationHandleFactory(
            metadata: $registry ?? $this->registry(),
            states: $this->relationStates,
        );
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
            relationLoader: $this->relationLoader(),
            relationStates: $this->relationStates,
            relationHandles: $this->relationHandles(),
        );
    }

    protected function manager(Database $database): EntityManager
    {
        $registry = $this->registry();

        return new EntityManager(
            database: $database,
            metadata: $registry,
            hydrator: new ReflectionHydrator(),
            persister: new ReflectionPersister(),
            relationLoader: $this->relationLoader($registry),
            relationStates: $this->relationStates,
            relationHandles: $this->relationHandles($registry),
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

    protected function createLibrary(?Connection $connection = null): void
    {
        foreach ([
            'CREATE TABLE writers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, writer_id INTEGER, editor_id INTEGER)',
            'CREATE TABLE jackets (id INTEGER PRIMARY KEY AUTOINCREMENT, book_id INTEGER, colour TEXT NOT NULL)',
            'CREATE TABLE chapters (id INTEGER PRIMARY KEY AUTOINCREMENT, book_id INTEGER, heading TEXT NOT NULL)',
            'CREATE TABLE plates (id INTEGER PRIMARY KEY AUTOINCREMENT, book_id INTEGER UNIQUE, name TEXT NOT NULL)',
            'CREATE TABLE topics (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
            'CREATE TABLE book_topic (book_id INTEGER NOT NULL, topic_id INTEGER)',
            'CREATE TABLE reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT NOT NULL, book_id INTEGER NOT NULL)',
        ] as $table) {
            $this->createTable($table, $connection);
        }
    }

    protected function seedLibrary(?Connection $connection = null): void
    {
        $connection ??= $this->connection;

        $connection->execute("INSERT INTO writers (name) VALUES ('Ursula'), ('Terry')");
        $connection->execute('INSERT INTO books (title, writer_id, editor_id) VALUES '
        . "('Earthsea', 1, 2), ('Discworld', 2, NULL), ('Lathe', 1, 2)");
        $connection->execute("INSERT INTO jackets (book_id, colour) VALUES (1, 'blue')");
        $connection->execute("INSERT INTO chapters (book_id, heading) VALUES (1, 'One'), (1, 'Two'), (2, 'Alpha')");
        $connection->execute("INSERT INTO plates (book_id, name) VALUES (1, 'front'), (NULL, 'back')");
        $connection->execute("INSERT INTO topics (name) VALUES ('fantasy'), ('scifi')");
        $connection->execute('INSERT INTO book_topic (book_id, topic_id) VALUES (1, 1), (1, 2), (2, 1)');
    }

    /**
     * @return list<string>
     */
    protected static function names(mixed $items, string $property): array
    {
        if (!is_iterable($items)) {
            self::fail(sprintf('Expected something to iterate over, got %s.', get_debug_type($items)));
        }

        $names = [];

        foreach ($items as $item) {
            if (!is_object($item)) {
                self::fail(sprintf('Expected a collection of entities, got %s.', get_debug_type($item)));
            }

            $value = new ReflectionProperty($item, $property)->getValue($item);

            if (!is_string($value)) {
                self::fail(sprintf('Expected "%s" to be a string, got %s.', $property, get_debug_type($value)));
            }

            $names[] = $value;
        }

        return $names;
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
