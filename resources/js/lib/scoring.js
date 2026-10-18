/**
 * Зеркало SetBasedRules::validate на клиенте: подсказать ошибку до отправки.
 * Сервер всё равно проверит, здесь только удобство.
 */
export function validateSets(rules, sets) {
    if (sets.length === 0) return 'Введите хотя бы одну партию';

    let home = 0;
    let away = 0;
    const maxSets = rules.sets_to_win * 2 - 1;

    for (const [index, set] of sets.entries()) {
        const number = index + 1;
        const deciding = number === maxSets;
        const target = deciding ? rules.points_in_deciding_set : rules.points_per_set;
        const [h, a] = set;

        if (![h, a].every((value) => Number.isSafeInteger(value) && value >= 0)) {
            return `Партия ${number}: очки должны быть целыми неотрицательными числами`;
        }

        if (h === a) return `Партия ${number}: ничья невозможна`;
        const winner = Math.max(h, a);
        const margin = Math.abs(h - a);
        if (winner < target) return `Партия ${number}: победитель должен набрать не меньше ${target}`;
        if (margin < rules.min_margin) return `Партия ${number}: разница должна быть не меньше ${rules.min_margin}`;
        if (winner > target && margin !== rules.min_margin) return `Партия ${number}: после ${target}:${target - rules.min_margin} партия идёт до разницы в ${rules.min_margin}`;

        if (h > a) home++; else away++;
        if ((home === rules.sets_to_win || away === rules.sets_to_win) && index < sets.length - 1) {
            return `Партия ${number} была решающей, лишние партии нужно убрать`;
        }
    }

    if (home !== rules.sets_to_win && away !== rules.sets_to_win) {
        return `Матч не окончен: нужно ${rules.sets_to_win} выигранных партии`;
    }
    return null;
}

export function setsWon(outcome, side) {
    if (!outcome || outcome.type !== 'sets') return null;
    return outcome.sets.filter(([h, a]) => (side === 'home' ? h > a : a > h)).length;
}

export function outcomeSummary(outcome) {
    if (!outcome) return '';
    if (outcome.type === 'walkover') return 'техническая победа';
    if (outcome.type === 'points') return `${outcome.home}:${outcome.away}`;
    return outcome.sets.map(([h, a]) => `${h}:${a}`).join(', ');
}
