<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Tournament;

enum ParticipantType: string
{
    case Player = 'player';
    case Pair = 'pair';
    case Team = 'team';
}
