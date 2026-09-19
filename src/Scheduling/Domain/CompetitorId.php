<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use Tournaments\Shared\Domain\Identifier;

/** Тот, кто играет. Расписанию не важно, игрок это, пара или команда: важно, что он не может быть в двух местах сразу. */
final class CompetitorId extends Identifier {}
