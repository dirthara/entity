<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Query\Grammar\SqlServerQueryGrammar;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

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
}
