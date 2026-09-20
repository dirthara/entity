<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Transaction\TransactionManager;

final class CountingConnection implements Connection
{
    /**
     * @var list<string>
     */
    public array $queries = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function execute(string $query, array $parameters = []): Result
    {
        $this->queries[] = $query;

        return $this->connection->execute($query, $parameters);
    }

    public function forget(): void
    {
        $this->queries = [];
    }

    /**
     * @return list<string>
     */
    public function selects(): array
    {
        return array_values(array_filter($this->queries, static fn(string $query): bool => str_starts_with(
            strtoupper(ltrim($query)),
            'SELECT',
        )));
    }

    public function lastInsertId(?string $sequence = null): ?string
    {
        return $this->connection->lastInsertId($sequence);
    }

    public function transactions(): TransactionManager
    {
        return $this->connection->transactions();
    }

    public function disconnect(): void
    {
        $this->connection->disconnect();
    }

    public function name(): string
    {
        return $this->connection->name();
    }

    public function driver(): DriverName
    {
        return $this->connection->driver();
    }
}
