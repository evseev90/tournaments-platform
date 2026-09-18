<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Stage;

enum StageStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
