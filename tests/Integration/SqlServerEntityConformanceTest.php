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
use Dirthara\Entity\Relation\Read\BelongsToOneReader;
use Dirthara\Entity\Type\Converter\DateTimeConverter;
use Dirthara\Entity\Relation\Handle\RelationRefresher;
use Dirthara\Entity\Relation\Read\BelongsToManyReader;
use Dirthara\Entity\Type\Converter\JsonArrayConverter;
use Dirthara\Entity\Type\Converter\BackedEnumConverter;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Query\Grammar\SqlServerQueryGrammar;
use Dirthara\Entity\Relation\DefaultRelationHandleFactory;
use Dirthara\Entity\Type\Converter\SerializedArrayConverter;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

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
#[Group('integration')]
final class SqlServerEntityConformanceTest extends EntityConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::SqlServer;
    }

    protected function driver(): Driver
    {
        return new SqlServerDriver($this->transactions());
    }

    protected function transactions(): TransactionGrammar
    {
        return new SqlServerTransactionGrammar(new SavepointPrefix());
    }

    protected function grammar(): QueryGrammar
    {
        return new SqlServerQueryGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::SqlServer,
            name: 'conformance',
            host: $this->env('DIRTHARA_SQLSRV_HOST', 'sqlserver'),
            port: (int) $this->env('DIRTHARA_SQLSRV_PORT', '1433'),
            database: $this->env('DIRTHARA_SQLSRV_DATABASE', 'master'),
            username: $this->env('DIRTHARA_SQLSRV_USERNAME', 'sa'),
            password: $this->env('DIRTHARA_SQLSRV_PASSWORD', 'Dirthara!2026'),
            dsn: ['TrustServerCertificate' => 'yes'],
        );
    }

    protected function recordsTable(): string
    {
        return 'CREATE TABLE conformance_records (
            id INT IDENTITY(1,1) PRIMARY KEY,
            display_name NVARCHAR(255) NOT NULL,
            active BIT NOT NULL,
            score FLOAT NOT NULL,
            meta NVARCHAR(MAX) NOT NULL,
            role NVARCHAR(32) NOT NULL,
            created_at DATETIME2 NOT NULL,
            born_on DATE NOT NULL,
            opens_at TIME NOT NULL,
            updated_at DATETIME2 NOT NULL,
            note NVARCHAR(255)
        )';
    }

    protected function membershipsTable(): string
    {
        return 'CREATE TABLE memberships (
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            role NVARCHAR(32) NOT NULL,
            PRIMARY KEY (team_id, user_id)
        )';
    }

    protected function readingsTable(): string
    {
        return 'CREATE TABLE conformance_readings (
            id INT IDENTITY(1,1) PRIMARY KEY,
            logged_at DATETIME2(3) NOT NULL,
            measured_at DATETIME2(6) NOT NULL,
            opens_at TIME(3) NOT NULL,
            settled_at DATETIME2(0) NOT NULL,
            sampled_at DATETIME2(6) NOT NULL
        )';
    }
}
