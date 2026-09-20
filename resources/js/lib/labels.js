export const sportLabels = {
    volleyball: 'Волейбол',
    beach_volleyball: 'Пляжный волейбол',
    table_tennis: 'Настольный теннис',
};

export const formatLabels = {
    single_elimination: 'Олимпийская система',
    double_elimination: 'Двойная сетка',
    round_robin: 'Круговая система',
};

export const participantTypeLabels = {
    player: 'Игроки',
    pair: 'Пары',
    team: 'Команды',
};

export const statusLabels = {
    draft: 'Набор участников',
    in_progress: 'Идёт',
    finished: 'Завершён',
};

export const bracketLabels = {
    winners: 'Верхняя сетка',
    losers: 'Нижняя сетка',
    grand_final: 'Гранд-финал',
};

/**
 * Подпись раунда двойной сетки. В верхней ветке считаем от финала, как в обычной сетке,
 * в нижней просто нумеруем, потому что «полуфинала нижней сетки» в обиходе нет.
 */
export function doubleRoundLabel(bracket, index, total) {
    if (bracket === 'grand_final') return 'Гранд-финал';
    if (index === total - 1) return bracket === 'winners' ? 'Финал верхней' : 'Финал нижней';
    if (bracket === 'losers') return `Нижняя, раунд ${index + 1}`;

    const fromEnd = total - 1 - index;
    if (fromEnd === 1) return 'Полуфинал верхней';
    if (fromEnd === 2) return '1/4 верхней';
    if (fromEnd === 3) return '1/8 верхней';
    return `1/${2 ** (fromEnd + 1)} верхней`;
}

export function roundLabel(stage, round) {
    if (stage.format !== 'single_elimination') {
        return `Тур ${round}`;
    }
    const fromEnd = stage.rounds.length - round;
    if (fromEnd === 0) return 'Финал';
    if (fromEnd === 1) return 'Полуфинал';
    if (fromEnd === 2) return '1/4 финала';
    if (fromEnd === 3) return '1/8 финала';
    return `1/${2 ** (fromEnd + 1)} финала`;
}

/** Подпись конкретного матча: в последнем раунде рядом с финалом живёт матч за третье место. */
export function fixtureLabel(stage, round, fixture) {
    if (stage.format === 'single_elimination' && round === stage.rounds.length && fixture.position === 1) {
        return 'Матч за 3 место';
    }
    if (fixture.bracket) {
        const inBracket = stage.rounds
            .map((r) => r.fixtures.filter((f) => f.bracket === fixture.bracket))
            .filter((list) => list.length > 0);
        const index = inBracket.findIndex((list) => list.some((f) => f.id === fixture.id));
        return doubleRoundLabel(fixture.bracket, index, inBracket.length);
    }
    return roundLabel(stage, round);
}

export function pluralize(n, one, few, many) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return `${n} ${one}`;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${n} ${few}`;
    return `${n} ${many}`;
}
