import { test } from 'node:test';
import assert from 'node:assert/strict';
import { CARD_HEIGHT, CARD_WIDTH, ROW_GAP, layoutBracket, layoutRounds } from '../../resources/js/lib/bracketLayout.js';

test('final and connectors stay centered between the larger match cards', () => {
    const stage = { rounds: [
        { number: 1, fixtures: [{ id: 'a', position: 0, nextWin: { fixture_id: 'f' } }, { id: 'b', position: 1, nextWin: { fixture_id: 'f' } }] },
        { number: 2, fixtures: [{ id: 'f', position: 0 }] },
    ] };
    const layout = layoutBracket(stage);
    const [a, b, final] = layout.fixtures;
    assert.equal(final.y, (a.y + b.y) / 2);
    assert.ok(b.y >= a.y + CARD_HEIGHT + ROW_GAP);
    for (const [index, edge] of layout.edges.entries()) {
        const parent = [a, b][index];
        assert.ok(edge.path.startsWith(`M ${parent.x + CARD_WIDTH} ${parent.y + CARD_HEIGHT / 2}`));
        assert.ok(edge.path.endsWith(`H ${final.x}`));
    }
});
test('third place remains separate and within the bracket container', () => {
    const layout = layoutBracket({ rounds: [{ number: 1, fixtures: [{ id: 'final', position: 0 }, { id: 'third', position: 1 }] }] });
    assert.equal(layout.fixtures.length, 1);
    assert.equal(layout.thirdPlace.fixture.id, 'third');
    assert.ok(layout.thirdPlace.y >= layout.fixtures[0].y + CARD_HEIGHT);
    assert.ok(layout.height >= layout.thirdPlace.y + CARD_HEIGHT);
});
test('an incoming link from another double-elimination branch does not break layout', () => {
    const layout = layoutRounds([{ number: 1, fixtures: [{ id: 'lower', nextWin: { fixture_id: 'other-branch' } }] }]);
    assert.equal(layout.edges.length, 0);
    assert.equal(layout.height, CARD_HEIGHT);
    assert.equal(layout.width, CARD_WIDTH);
});
