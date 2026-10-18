import { test } from 'node:test';
import assert from 'node:assert/strict';
import { setsWon, validateSets } from '../../resources/js/lib/scoring.js';

const rules = { sets_to_win: 3, points_per_set: 11, points_in_deciding_set: 11, min_margin: 2 };
test('a valid result yields the same tally that is saved', () => {
    const sets = [[11, 5], [11, 8], [12, 14], [13, 11]];
    assert.equal(validateSets(rules, sets), null);
    assert.equal(setsWon({ type: 'sets', sets }, 'home'), 3);
    assert.equal(setsWon({ type: 'sets', sets }, 'away'), 1);
});
test('a tied set and an unfinished match cannot be saved', () => {
    assert.match(validateSets(rules, [[11, 11]]), /ничья/);
    assert.match(validateSets(rules, [[11, 5], [5, 11]]), /не окончен/);
    assert.match(validateSets(rules, []), /Введите/);
});
test('non-integer, negative and non-finite scores are rejected', () => {
    for (const value of [-1, 1.5, NaN, Infinity, Number.MAX_SAFE_INTEGER + 1]) {
        assert.match(validateSets(rules, [[11, value], [11, 5], [11, 5]]), /целыми неотрицательными/);
    }
});
test('extra sets after the winning set are not silently discarded', () => {
    assert.match(validateSets(rules, [[11, 5], [11, 5], [11, 5], [11, 5]]), /лишние партии/);
});
test('a deciding set uses the configured target', () => {
    const volleyball = { ...rules, points_per_set: 25, points_in_deciding_set: 15 };
    assert.equal(validateSets(volleyball, [[25, 20], [20, 25], [25, 20], [20, 25], [15, 13]]), null);
    assert.match(validateSets(volleyball, [[25, 20], [20, 25], [25, 20], [20, 25], [14, 12]]), /не меньше 15/);
});
test('extended sets must finish at the required margin', () => {
    assert.equal(validateSets(rules, [[16, 14], [11, 5], [11, 5]]), null);
    assert.match(validateSets(rules, [[16, 12], [11, 5], [11, 5]]), /идёт до разницы/);
});
