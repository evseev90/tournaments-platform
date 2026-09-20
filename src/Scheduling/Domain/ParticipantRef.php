<?php

declare(strict_types=1);

namespace Tournaments\Scheduling\Domain;

use Tournaments\Shared\Domain\Identifier;

/**
 * Ссылка на участника турнира. Расписанию не важно, игрок это, пара или команда:
 * важно, что он не может быть в двух местах сразу.
 */
final class ParticipantRef extends Identifier {}
