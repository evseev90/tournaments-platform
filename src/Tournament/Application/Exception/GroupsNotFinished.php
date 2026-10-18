<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Exception;

use Tournaments\Tournament\Domain\Exception\TournamentDomainException;

class GroupsNotFinished extends TournamentDomainException {}
