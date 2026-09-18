<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

use InvalidArgumentException;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;

/**
 * Правила матча по партиям. Матч идёт до setsToWin выигранных партий,
 * партия до pointsPerSet очков (решающая — до pointsInDecidingSet) с разницей minMargin.
 * При счёте «на больше-меньше» партия продолжается, пока разница не станет ровно minMargin.
 */
final readonly class SetBasedRules implements ScoringRules
{
    public const string TYPE = 'sets';

    public function __construct(
        public int $setsToWin,
        public int $pointsPerSet,
        public int $pointsInDecidingSet,
        public int $minMargin = 2,
    ) {
        if ($setsToWin < 1 || $pointsPerSet < 1 || $pointsInDecidingSet < 1 || $minMargin < 1) {
            throw new InvalidArgumentException('Scoring rules values must be positive');
        }
    }

    public static function volleyball(): self
    {
        return new self(setsToWin: 3, pointsPerSet: 25, pointsInDecidingSet: 15);
    }

    public static function beachVolleyball(): self
    {
        return new self(setsToWin: 2, pointsPerSet: 21, pointsInDecidingSet: 15);
    }

    public static function tableTennis(): self
    {
        return new self(setsToWin: 3, pointsPerSet: 11, pointsInDecidingSet: 11);
    }

    public function maxSets(): int
    {
        return $this->setsToWin * 2 - 1;
    }

    public function validate(MatchOutcome $outcome): void
    {
        if ($outcome instanceof Walkover) {
            return;
        }
        if (! $outcome instanceof SetBasedOutcome) {
            throw new InvalidOutcome(sprintf('Expected set-based outcome, got "%s"', $outcome->type()));
        }

        $winner = $outcome->winner();
        if ($winner === null) {
            throw new InvalidOutcome('Match cannot end in a draw');
        }

        $winnerSets = $outcome->setsWon($winner);
        $loserSets = $outcome->setsWon($winner->opposite());
        if ($winnerSets !== $this->setsToWin) {
            throw new InvalidOutcome(sprintf('Winner must take exactly %d sets, got %d', $this->setsToWin, $winnerSets));
        }
        if (count($outcome->sets) !== $winnerSets + $loserSets) {
            throw new InvalidOutcome('Unexpected number of sets');
        }

        $lastIndex = count($outcome->sets) - 1;
        foreach ($outcome->sets as $index => $set) {
            $this->validateSet($set, $index + 1, $index === $lastIndex && count($outcome->sets) === $this->maxSets());
        }
    }

    private function validateSet(SetScore $set, int $number, bool $deciding): void
    {
        $target = $deciding ? $this->pointsInDecidingSet : $this->pointsPerSet;
        $winnerPoints = $set->pointsOf($set->winner());

        if ($winnerPoints < $target) {
            throw new InvalidOutcome(sprintf('Set %d: winner needs at least %d points, got %d:%d', $number, $target, $set->home, $set->away));
        }
        if ($set->margin() < $this->minMargin) {
            throw new InvalidOutcome(sprintf('Set %d: margin must be at least %d, got %d:%d', $number, $this->minMargin, $set->home, $set->away));
        }
        if ($winnerPoints > $target && $set->margin() !== $this->minMargin) {
            throw new InvalidOutcome(sprintf('Set %d: after deuce the set ends with margin of exactly %d, got %d:%d', $number, $this->minMargin, $set->home, $set->away));
        }
    }

    public function allowsDraw(): bool
    {
        return false;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'sets_to_win' => $this->setsToWin,
            'points_per_set' => $this->pointsPerSet,
            'points_in_deciding_set' => $this->pointsInDecidingSet,
            'min_margin' => $this->minMargin,
        ];
    }
}
