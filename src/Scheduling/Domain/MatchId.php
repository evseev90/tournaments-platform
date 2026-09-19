<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use Tournaments\Shared\Domain\Identifier;

/** Ссылка на матч из контекста «Турнир». Здесь это просто идентификатор того, что нужно поставить в сетку времени. */
final class MatchId extends Identifier {}
