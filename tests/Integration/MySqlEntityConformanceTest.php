<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Query\Grammar\MySqlQueryGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

#[Group('conformance')]
#[Group('integration')]
final class MySqlEntityConformanceTest extends EntityConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::MySql;
    }

    protected function driver(): Driver
    {
        return new MySqlDriver($this->transactions());
    }

    protected function grammar(): QueryGrammar
    {
        return new MySqlQueryGrammar();
    }

    protected function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::MySql,
            name: 'conformance',
            host: $this->env('DIRTHARA_MYSQL_HOST', 'mysql'),
            port: (int) $this->env('DIRTHARA_MYSQL_PORT', '3306'),
            database: $this->env('DIRTHARA_MYSQL_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_MYSQL_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_MYSQL_PASSWORD', 'dirthara'),
        );
    }

    protected function recordsTable(): string
    {
        return 'CREATE TABLE conformance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            display_name VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL,
            score DOUBLE NOT NULL,
            meta TEXT NOT NULL,
            role VARCHAR(32) NOT NULL,
            note VARCHAR(255)
        )';
    }

    protected function membershipsTable(): string
    {
        return 'CREATE TABLE memberships (
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(32) NOT NULL,
            PRIMARY KEY (team_id, user_id)
        )';
    }
}
