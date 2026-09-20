/**
 * Раскладка олимпийской сетки.
 *
 * Позиции считаются по графу nextWin, а не по номеру позиции в раунде: матч следующего
 * раунда встаёт ровно посередине между теми двумя, что в него ведут. При bye это важно,
 * потому что проходы занимают место в сетке наравне с играми.
 */

export const CARD_HEIGHT = 66;
export const CARD_WIDTH = 224;
export const COLUMN_GAP = 44;
export const ROW_GAP = 30;

/** Отступ от финала до матча за третье место: в него помещается заголовок. */
export const THIRD_PLACE_GAP = 46;

/**
 * @returns {{
 *   fixtures: Array<{fixture: object, x: number, y: number, round: number}>,
 *   edges: Array<{path: string, active: boolean}>,
 *   thirdPlace: {fixture: object, x: number, y: number}|null,
 *   width: number,
 *   height: number,
 *   columns: Array<{round: number, x: number}>,
 * }}
 */
export function layoutBracket(stage) {
    const lastRound = stage.rounds.length;
    const playoff = stage.rounds.map((round) => ({
        number: round.number,
        // Матч за третье место живёт в последнем раунде на позиции 1: в сетку он не входит.
        fixtures: round.number === lastRound ? round.fixtures.filter((f) => f.position === 0) : round.fixtures,
    }));

    const thirdPlaceFixture = stage.rounds.at(-1)?.fixtures.find((f) => f.position === 1) ?? null;

    return layoutRounds(playoff, thirdPlaceFixture);
}

/**
 * Раскладка произвольного набора раундов. Ветка двойной сетки раскладывается тем же кодом:
 * связи внутри неё считаются по nextWin, а входы из соседней ветки просто не находятся.
 *
 * @param {Array<{number: number, fixtures: Array<object>}>} playoff
 * @param {object|null} thirdPlaceFixture
 */
export function layoutRounds(playoff, thirdPlaceFixture = null) {

    const parentsOf = new Map();
    for (const round of playoff) {
        for (const fixture of round.fixtures) {
            const next = fixture.nextWin?.fixture_id;
            if (!next) continue;
            if (!parentsOf.has(next)) parentsOf.set(next, []);
            parentsOf.get(next).push(fixture);
        }
    }

    const positions = new Map();
    const placed = [];

    for (const round of playoff) {
        const x = (round.number - 1) * (CARD_WIDTH + COLUMN_GAP);

        round.fixtures.forEach((fixture, index) => {
            const parents = parentsOf.get(fixture.id) ?? [];
            const known = parents.map((p) => positions.get(p.id)?.y).filter((y) => y !== undefined);

            const y = known.length > 0
                ? known.reduce((sum, value) => sum + value, 0) / known.length
                : index * (CARD_HEIGHT + ROW_GAP);

            positions.set(fixture.id, { x, y });
            placed.push({ fixture, x, y, round: round.number });
        });
    }

    const edges = [];
    for (const { fixture } of placed) {
        const next = fixture.nextWin?.fixture_id;
        const child = next ? positions.get(next) : null;
        const parent = positions.get(fixture.id);
        if (!child || !parent) continue;

        edges.push({
            path: connector(parent.x + CARD_WIDTH, parent.y + CARD_HEIGHT / 2, child.x, child.y + CARD_HEIGHT / 2),
            active: Boolean(fixture.winnerId),
        });
    }

    // Матч за третье место в сетку не входит: ставим его отдельной карточкой под финалом.
    const finalPosition = placed.at(-1);
    const thirdPlace = thirdPlaceFixture && finalPosition
        ? {
            fixture: thirdPlaceFixture,
            x: finalPosition.x,
            y: finalPosition.y + CARD_HEIGHT + THIRD_PLACE_GAP,
        }
        : null;

    const bottom = [...placed, ...(thirdPlace ? [thirdPlace] : [])]
        .reduce((max, item) => Math.max(max, item.y + CARD_HEIGHT), 0);

    return {
        fixtures: placed,
        edges,
        thirdPlace,
        width: playoff.length * CARD_WIDTH + (playoff.length - 1) * COLUMN_GAP,
        height: bottom,
        columns: playoff.map((round) => ({ round: round.number, x: (round.number - 1) * (CARD_WIDTH + COLUMN_GAP) })),
    };
}

/** Ортогональная линия из трёх отрезков со скруглёнными углами. */
function connector(x1, y1, x2, y2) {
    const middle = x1 + (x2 - x1) / 2;

    if (Math.abs(y2 - y1) < 1) {
        return `M ${x1} ${y1} H ${x2}`;
    }

    const direction = y2 > y1 ? 1 : -1;
    const radius = Math.min(8, Math.abs(y2 - y1) / 2, Math.abs(middle - x1));

    return [
        `M ${x1} ${y1}`,
        `H ${middle - radius}`,
        `Q ${middle} ${y1} ${middle} ${y1 + direction * radius}`,
        `V ${y2 - direction * radius}`,
        `Q ${middle} ${y2} ${middle + radius} ${y2}`,
        `H ${x2}`,
    ].join(' ');
}
