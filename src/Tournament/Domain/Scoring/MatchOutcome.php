<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

/**
 * Результат матча. Сетки и таблицы работают только с этим интерфейсом
 * и не знают, чем именно закончился матч: партиями, голами или неявкой.
 */
interface MatchOutcome
{
    /** Победитель или null при ничьей. */
    public function winner(): ?Side;

    public function isDraw(): bool;

    public function type(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;

    public function equals(MatchOutcome $other): bool;
}
