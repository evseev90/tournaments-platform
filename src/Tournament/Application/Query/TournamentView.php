<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

/**
 * Полное представление турнира для страниц организатора и зрителей.
 * Токена организатора здесь нет намеренно: публичная страница его видеть не должна.
 */
final readonly class TournamentView
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  list<ParticipantView>  $participants
     * @param  list<StageView>  $stages
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $sport,
        public array $rules,
        public string $status,
        public string $publicSlug,
        public string $createdAt,
        public ?int $ownerId,
        public array $participants,
        public array $stages,
    ) {}
}
