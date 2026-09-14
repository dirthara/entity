<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

/**
 * SQLite needs no service, so this is the one conformance run that always happens.
 */
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
