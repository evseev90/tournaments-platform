<?php

declare(strict_types=1);

namespace Tournaments\Shared\Application;

use Closure;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function transactional(Closure $operation): mixed;
}
