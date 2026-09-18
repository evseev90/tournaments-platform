<?php

declare(strict_types=1);

namespace App\Support;

use Tournaments\Shared\Domain\DomainException;
use Tournaments\Tournament\Application\Exception\NoStageGenerated;
use Tournaments\Tournament\Application\Exception\StageAlreadyGenerated;
use Tournaments\Tournament\Application\Exception\StageHasResults;
use Tournaments\Tournament\Domain\Exception\CannotRevertResult;
use Tournaments\Tournament\Domain\Exception\FixtureNotReady;
use Tournaments\Tournament\Domain\Exception\InvalidName;
use Tournaments\Tournament\Domain\Exception\InvalidOutcome;
use Tournaments\Tournament\Domain\Exception\NotEnoughParticipants;
use Tournaments\Tournament\Domain\Exception\ParticipantNotFound;
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
            NoStageGenerated::class => 'Сетка ещё не сгенерирована.',
            default => 'Действие невозможно: '.$exception->getMessage(),
        };
    }
}
