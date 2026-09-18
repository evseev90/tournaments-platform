<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

final readonly class CreateTournament
{
    /** @param array<string, mixed>|null $rules null — правила по умолчанию для вида спорта */
    public function __construct(
        public string $name,
        public string $sport,
        public ?array $rules = null,
    ) {}
}
