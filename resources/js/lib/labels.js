export const sportLabels = {
    volleyball: 'Волейбол',
    beach_volleyball: 'Пляжный волейбол',
    table_tennis: 'Настольный теннис',
};

export const formatLabels = {
    single_elimination: 'Олимпийская система',
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

export function pluralize(n, one, few, many) {
    const mod10 = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return `${n} ${one}`;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${n} ${few}`;
    return `${n} ${many}`;
}
