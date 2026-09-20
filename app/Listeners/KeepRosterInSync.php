<?php

declare(strict_types=1);

namespace App\Listeners;

use Tournaments\Statistics\Application\Command\SyncCompetitors;
use Tournaments\Statistics\Application\Command\SyncCompetitorsHandler;
use Tournaments\Tournament\Domain\Event\ParticipantAdded;
use Tournaments\Tournament\Domain\Event\ParticipantRemoved;

/** Состав турнира меняется — справочник аккаунта подтягивается следом. */
final readonly class KeepRosterInSync
{
    public function __construct(
        private SyncCompetitorsHandler $sync,
    ) {}

    public function onParticipantAdded(ParticipantAdded $event): void
    {
        ($this->sync)(new SyncCompetitors($event->tournamentId->value));
    }

    public function onParticipantRemoved(ParticipantRemoved $event): void
    {
        ($this->sync)(new SyncCompetitors($event->tournamentId->value));
    }
}
