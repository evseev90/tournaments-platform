<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Query;

final readonly class StageView
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<RoundView>  $rounds
     * @param  list<StandingView>|null  $standings  таблица, только для круговой
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public int $phase,
        public string $format,
        public array $settings,
        public string $status,
        public array $rounds,
        public ?array $standings,
        /** Текущий тур доигран, ждёт жеребьёвки следующего. Только для швейцарской системы. */
        public bool $awaitsNextRound,
        public int $plannedRounds,
    ) {}
}
