<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

/** Результат матча, сыгранного по партиям: волейбол, теннис, бадминтон. */
final readonly class SetBasedOutcome implements MatchOutcome
{
    public const string TYPE = 'sets';

    /** @var list<SetScore> */
    public array $sets;

    /** @param list<SetScore> $sets */
    public function __construct(array $sets)
    {
        if ($sets === []) {
            throw new InvalidOutcome('Outcome must contain at least one set');
        }
        $this->sets = $sets;
    }

    /** @param list<array{0: int, 1: int}> $sets */
    public static function of(array $sets): self
    {
        return new self(array_map(static fn (array $s) => new SetScore($s[0], $s[1]), $sets));
    }

    public function setsWon(Side $side): int
    {
        return count(array_filter($this->sets, static fn (SetScore $s) => $s->winner() === $side));
    }

    public function pointsWon(Side $side): int
    {
        return array_sum(array_map(static fn (SetScore $s) => $s->pointsOf($side), $this->sets));
    }

    public function winner(): ?Side
    {
        $home = $this->setsWon(Side::Home);
        $away = $this->setsWon(Side::Away);

        return $home === $away ? null : ($home > $away ? Side::Home : Side::Away);
    }

    public function isDraw(): bool
    {
        return $this->winner() === null;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'sets' => array_map(static fn (SetScore $s) => [$s->home, $s->away], $this->sets),
        ];
    }

    public function equals(MatchOutcome $other): bool
    {
        return $other instanceof self && $this->toArray() === $other->toArray();
    }
}
