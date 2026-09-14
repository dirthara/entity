<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Query\Grammar\PostgresSqlQueryGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

/**
 * The only run where a generated identifier comes back through `RETURNING`.
 */
#[Group('conformance')]
#[Group('integration')]
final class PostgresSqlEntityConformanceTest extends EntityConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::PostgresSql;
    }

    protected function driver(): Driver
    {
        return new PostgresSqlDriver($this->transactions());
    }

    protected function grammar(): QueryGrammar
    {
        return new PostgresSqlQueryGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::PostgresSql,
            name: 'conformance',
            host: $this->env('DIRTHARA_POSTGRES_HOST', 'postgres'),
            port: (int) $this->env('DIRTHARA_POSTGRES_PORT', '5432'),
            database: $this->env('DIRTHARA_POSTGRES_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_POSTGRES_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_POSTGRES_PASSWORD', 'dirthara'),
        );
    }

    protected function recordsTable(): string
    {
        return 'CREATE TABLE conformance_records (
            id SERIAL PRIMARY KEY,
            display_name VARCHAR(255) NOT NULL,
            active BOOLEAN NOT NULL,
            score DOUBLE PRECISION NOT NULL,
            meta TEXT NOT NULL,
            role VARCHAR(32) NOT NULL,
            note VARCHAR(255)
        )';
    }

    protected function membershipsTable(): string
    {
        return 'CREATE TABLE memberships (
            team_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role VARCHAR(32) NOT NULL,
            PRIMARY KEY (team_id, user_id)
        )';
    }
}
