<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Tournaments\Shared\Application\TransactionManager;

final class ImmediateTransactionManager implements TransactionManager
{
    public int $calls = 0;

    public function transactional(Closure $operation): mixed
    {
        $this->calls++;

        return $operation();
    }
}
