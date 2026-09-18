<?php

declare(strict_types=1);

namespace Tournaments\Tournament\Domain\Scoring;

enum Sport: string
{
    case Volleyball = 'volleyball';
    case BeachVolleyball = 'beach_volleyball';
    case TableTennis = 'table_tennis';

    public function defaultRules(): ScoringRules
    {
        return match ($this) {
            self::Volleyball => SetBasedRules::volleyball(),
            self::BeachVolleyball => SetBasedRules::beachVolleyball(),
            self::TableTennis => SetBasedRules::tableTennis(),
        };
    }
}
