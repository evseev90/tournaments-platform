<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

use InvalidArgumentException;
use Tournaments\Tournament\Domain\Exception\TournamentNotFound;
use Tournaments\Tournament\Domain\Stage\StageRepository;
use Tournaments\Tournament\Domain\Tournament\AdminToken;
use Tournaments\Tournament\Domain\Tournament\PublicSlug;
use Tournaments\Tournament\Domain\Tournament\Tournament;
use Tournaments\Tournament\Domain\Tournament\TournamentId;
use Tournaments\Tournament\Domain\Tournament\TournamentRepository;

/**
 * Чтение турнира для страниц. Собирает DTO из агрегатов: данные одного турнира малы,
 * отдельные SQL-модели чтения понадобятся для списков и статистики.
 */
final readonly class TournamentFinder
{
    public function __construct(
        private TournamentRepository $tournaments,
        private StageRepository $stages,
        private TournamentViewAssembler $assembler = new TournamentViewAssembler,
    ) {}

    public function byId(string $id): TournamentView
    {
        return $this->view($this->tournaments->get(TournamentId::fromString($id)));
    }

    /** Ссылка организатора. Кривой или чужой токен — null, без различия. */
    public function byAdminToken(string $token): ?TournamentView
    {
        try {
            $tournament = $this->tournaments->findByAdminToken(AdminToken::fromString($token));
        } catch (InvalidArgumentException) {
            return null;
        }

        return $tournament === null ? null : $this->view($tournament);
    }

    public function bySlug(string $slug): ?TournamentView
    {
        try {
            $tournament = $this->tournaments->findBySlug(PublicSlug::fromString($slug));
        } catch (InvalidArgumentException) {
            return null;
        }

        return $tournament === null ? null : $this->view($tournament);
    }

    /** @throws TournamentNotFound */
    private function view(Tournament $tournament): TournamentView
    {
        return $this->assembler->assemble($tournament, $this->stages->ofTournament($tournament->id));
    }
}
