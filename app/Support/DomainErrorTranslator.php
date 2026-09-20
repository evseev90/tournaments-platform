<?php

declare(strict_types=1);

namespace App\Support;

use Tournaments\Scheduling\Domain\Exception\CompetitorBusy;
use Tournaments\Scheduling\Domain\Exception\InvalidVenueName;
use Tournaments\Scheduling\Domain\Exception\MatchNotScheduled;
use Tournaments\Scheduling\Domain\Exception\NothingToPlan;
use Tournaments\Scheduling\Domain\Exception\NoVenues;
use Tournaments\Scheduling\Domain\Exception\ScheduleNotFound;
use Tournaments\Scheduling\Domain\Exception\VenueBusy;
use Tournaments\Scheduling\Domain\Exception\VenueNotFound;
use Tournaments\Shared\Domain\DomainException;
use Tournaments\Tournament\Application\Exception\GroupsNotFinished;
use Tournaments\Tournament\Application\Exception\NoStageGenerated;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Application\Exception\StageHasResults;
use Tournaments\Tournament\Domain\Exception\CannotPairRound;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\FixtureNotReady;
use Tournaments\Tournament\Domain\Exception\InvalidName;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\ParticipantNotFound;
use Tournaments\Tournament\Domain\Exception\RoundNotFinished;
use Tournaments\Tournament\Domain\Exception\TournamentLocked;

/** Доменные ошибки говорят по-английски для логов, пользователю показываем русский текст. */
final class DomainErrorTranslator
{
    public static function translate(DomainException $exception): string
    {
        return match ($exception::class) {
            InvalidOutcome::class => 'Счёт не соответствует правилам: '.$exception->getMessage(),
            InvalidName::class => 'Название не может быть пустым и длиннее 100 символов.',
            NotEnoughParticipants::class => 'Слишком мало участников для этого формата.',
            TournamentLocked::class => 'Турнир уже начался: состав и правила менять нельзя.',
            ParticipantNotFound::class => 'Участник не найден.',
            FixtureNotReady::class => 'В этом матче ещё не известны оба участника.',
            CannotRevertResult::class => 'Результат нельзя отменить: следующий матч уже сыгран.',
            StageAlreadyGenerated::class => 'Сетка уже сгенерирована. Сначала отмените её.',
            StageHasResults::class => 'Нельзя отменить сетку, в которой уже есть результаты.',
            NoStageGenerated::class, GroupsNotFinished::class, CannotPairRound::class, RoundNotFinished::class => $exception->getMessage(),
            NoVenues::class => 'Добавьте хотя бы одну площадку.',
            VenueNotFound::class => 'Площадка не найдена.',
            VenueBusy::class => 'На этой площадке в это время уже идёт другой матч.',
            CompetitorBusy::class => 'Участник в это время уже играет другой матч.',
            MatchNotScheduled::class => 'Этого матча нет в расписании.',
            InvalidVenueName::class => 'Название площадки не может быть пустым и длиннее 60 символов.',
            ScheduleNotFound::class, NothingToPlan::class => $exception->getMessage(),
            default => 'Действие невозможно: '.$exception->getMessage(),
        };
    }
}
