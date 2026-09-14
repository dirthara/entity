<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Transaction\TransactionManager;

/**
 * A connection that runs every query for real but never reports a generated key,
 * which is how a driver behaves when the insert produced no identifier.
 */
final readonly class NullIdentifierConnection implements Connection
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function execute(string $query, array $parameters = []): Result
    {
        return $this->connection->execute($query, $parameters);
    }

    public function lastInsertId(?string $sequence = null): ?string
    {
        return null;
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
