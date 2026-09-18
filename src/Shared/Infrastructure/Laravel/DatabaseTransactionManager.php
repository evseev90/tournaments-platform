<?php

declare(strict_types=1);

namespace Tournaments\Shared\Infrastructure\Laravel;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Tournaments\Shared\Application\TransactionManager;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function transactional(Closure $operation): mixed
    {
        return $this->connection->transaction($operation);
    }
}
