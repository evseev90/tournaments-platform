<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

/** Участник турнира: игрок, пара или команда. Состав — просто список имён. */
final class Participant
{
    /** @var list<string> */
    public private(set) array $members;

    /** @param list<string> $members */
    public function __construct(
        public readonly ParticipantId $id,
        public private(set) Name $name,
        public readonly ParticipantType $type,
        array $members,
        public private(set) int $seed,
    ) {
        $this->members = self::normalizeMembers($members);
    }

    /** @param list<string> $members */
    public function update(Name $name, array $members): void
    {
        $this->name = $name;
        $this->members = self::normalizeMembers($members);
    }

    public function reseed(int $seed): void
    {
        $this->seed = $seed;
    }

    /**
     * @param  list<string>  $members
     * @return list<string>
     */
    private static function normalizeMembers(array $members): array
    {
        $normalized = array_map(static fn (string $m) => trim($m), $members);

        return array_values(array_filter($normalized, static fn (string $m) => $m !== ''));
    }
}
