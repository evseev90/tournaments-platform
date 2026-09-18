<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Application\Command;

/** Что нужно организатору сразу после создания: обе ссылки. */
final readonly class CreatedTournament
{
    public function __construct(
        public string $id,
        public string $adminToken,
        public string $publicSlug,
    ) {}
}
