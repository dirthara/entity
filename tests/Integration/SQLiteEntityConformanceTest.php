<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use Dirthara\Entity\EntityStore;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Query\EntityQuery;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Relation\Relations;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Entity\Type\TemporalFormat;
use Dirthara\Entity\Relation\RelationTree;
use PHPUnit\Framework\Attributes\UsesClass;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\MetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Entity\Relation\Read\RelatedRows;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Entity\Relation\Read\HasOneReader;
use Dirthara\Entity\Metadata\IdentifierMetadata;
use Dirthara\Entity\Relation\Read\HasManyReader;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Type\Converter\FloatConverter;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Entity\Relation\DefaultRelationLoader;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Type\Converter\StringConverter;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Type\Converter\BooleanConverter;
use Dirthara\Entity\Type\Converter\IntegerConverter;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Entity\Relation\Read\BelongsToOneReader;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Relation\Handle\RelationRefresher;
use Dirthara\Entity\Relation\Read\BelongsToManyReader;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

#[CoversClass(EntityStore::class)]
#[CoversClass(ReflectionHydrator::class)]
#[CoversClass(ReflectionPersister::class)]
#[CoversClass(EntityQuery::class)]
#[UsesClass(Column::class)]
#[UsesClass(Entity::class)]
#[UsesClass(Id::class)]
#[UsesClass(EntityManager::class)]
#[UsesClass(EntityMetadata::class)]
#[UsesClass(IdentifierMetadata::class)]
#[UsesClass(MetadataFactory::class)]
#[UsesClass(MetadataRegistry::class)]
#[UsesClass(PropertyMetadata::class)]
#[UsesClass(DefaultNamingStrategy::class)]
#[UsesClass(DefaultRelationHandleFactory::class)]
#[UsesClass(DefaultRelationLoader::class)]
#[UsesClass(RelationRefresher::class)]
#[UsesClass(BelongsToManyReader::class)]
#[UsesClass(BelongsToOneReader::class)]
#[UsesClass(HasManyReader::class)]
#[UsesClass(HasOneReader::class)]
#[UsesClass(RelatedRows::class)]
#[UsesClass(RelationStateRegistry::class)]
#[UsesClass(RelationTree::class)]
#[UsesClass(Relations::class)]
#[UsesClass(BackedEnumConverter::class)]
#[UsesClass(BooleanConverter::class)]
#[UsesClass(DateTimeConverter::class)]
#[UsesClass(FloatConverter::class)]
#[UsesClass(IntegerConverter::class)]
#[UsesClass(JsonArrayConverter::class)]
#[UsesClass(SerializedArrayConverter::class)]
#[UsesClass(StringConverter::class)]
#[UsesClass(TemporalFormat::class)]
#[UsesClass(TypeRegistry::class)]
#[Group('conformance')]
final class SQLiteEntityConformanceTest extends EntityConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::SQLite;
    }

    protected function driver(): Driver
    {
        return new SQLiteDriver($this->transactions());
    }

    protected function grammar(): QueryGrammar
    {
        return new SQLiteQueryGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: 'conformance', database: ':memory:');
    }

    protected function recordsTable(): string
    {
        return 'CREATE TABLE conformance_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            display_name TEXT NOT NULL,
            active INTEGER NOT NULL,
            score REAL NOT NULL,
            meta TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at TEXT NOT NULL,
            born_on TEXT NOT NULL,
            opens_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            note TEXT
        )';
    }

    protected function membershipsTable(): string
    {
        return 'CREATE TABLE memberships (
            team_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            PRIMARY KEY (team_id, user_id)
        )';
    }
}
