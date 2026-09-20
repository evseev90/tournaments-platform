<?php

declare(strict_types=1);

namespace Tournaments\Statistics\Application\Command;

use Tournaments\Shared\Application\TransactionManager;
use Tournaments\Statistics\Domain\Roster\Competitor;
use Tournaments\Statistics\Domain\Roster\CompetitorId;
use Tournaments\Statistics\Domain\Roster\CompetitorRepository;
use Tournaments\Statistics\Domain\Roster\RosterEntry;
use Tournaments\Statistics\Domain\Roster\TournamentRoster;

/**
 * Участники анонимных турниров в статистику не попадают: складывать их некуда.
 * Как только турнир привязан к аккаунту, его состав появляется в справочнике этого аккаунта.
 */
final readonly class SyncCompetitorsHandler
{
    public function __construct(
        private CompetitorRepository $competitors,
        private TournamentRoster $roster,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(SyncCompetitors $command): void
    {
        $owner = $this->roster->ownerOf($command->tournamentId);
        if ($owner === null) {
            return;
        }

        $participants = $this->roster->participantsOf($command->tournamentId);
        if ($participants === []) {
            return;
        }

        $this->transaction->transactional(function () use ($owner, $participants): void {
            $links = [];
            foreach ($participants as $entry) {
                $links[$entry->participantId] = $this->resolve($owner, $entry)->id->value;
            }
            $this->roster->link($links);
        });
    }

    private function resolve(int $owner, RosterEntry $entry): Competitor
    {
        $normalized = Competitor::normalize($entry->name);
        $existing = $this->competitors->findByName($owner, $entry->type, $normalized);
        if ($existing !== null) {
            return $existing;
        }

        $competitor = new Competitor(CompetitorId::generate(), $owner, $entry->name, $entry->type);
        $this->competitors->save($competitor);

        return $competitor;
    }
}
