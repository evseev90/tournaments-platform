import { after, before, test } from 'node:test';
import assert from 'node:assert/strict';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

let server;
let FixtureCard;
let BracketRoundsList;
let PhaseList;
before(async () => {
    server = await createServer({
        configFile: false,
        cacheDir: join(tmpdir(), `tournaments-ui-tests-${process.pid}`),
        plugins: [vue()],
        server: { middlewareMode: true, watch: null },
        appType: 'custom',
    });
    FixtureCard = (await server.ssrLoadModule('/resources/js/Components/FixtureCard.vue')).default;
    BracketRoundsList = (await server.ssrLoadModule('/resources/js/Components/BracketRoundsList.vue')).default;
    PhaseList = (await server.ssrLoadModule('/resources/js/Components/PhaseList.vue')).default;
});
after(async () => { await server?.close(); });

const names = { a: 'Анна', b: 'Борис' };
const fixture = (id, status = 'ready', position = 0) => ({ id, status, position, homeId: 'a', awayId: 'b', outcome: null });
const render = (component, props) => renderToString(createSSRApp({ render: () => h(component, props) }));

test('the selected match has a visible editing state and an accessible pressed state', async () => {
    const html = await render(FixtureCard, { fixture: fixture('f'), names, editable: true, selected: true });
    assert.match(html, /Редактируется/);
    assert.match(html, /aria-pressed="true"/);
    assert.match(html, /ring-accent/);
    const unselected = await render(FixtureCard, { fixture: fixture('f'), names, editable: true });
    assert.doesNotMatch(unselected, /Редактируется/);
    assert.match(unselected, /aria-pressed="false"/);
});

test('mobile navigation starts at a ready round and counts matches without byes', async () => {
    const html = await render(BracketRoundsList, {
        names,
        rounds: [
            { number: 1, label: 'Первый раунд', fixtures: [fixture('done', 'finished')] },
            { number: 2, label: 'Финал', fixtures: [fixture('ready'), fixture('bye', 'bye', 1)] },
        ],
    });
    assert.match(html, /Раунд 2 из 2 · Сыграно 0 из 1 матчей/);
    assert.match(html, /disabled[^>]*>Следующий/);
    assert.doesNotMatch(html, /disabled[^>]*>← Предыдущий/);
});

test('opening a match selects its round even when another round has ready fixtures', async () => {
    const html = await render(BracketRoundsList, {
        names, editable: true, selectedFixtureId: 'chosen',
        rounds: [
            { number: 1, label: 'Первый раунд', fixtures: [fixture('chosen', 'finished')] },
            { number: 'third-place', label: 'За третье место', fixtures: [fixture('ready')] },
        ],
    });
    assert.match(html, /Раунд 1 из 2 · Сыграно 1 из 1 матчей/);
    assert.match(html, /Редактируется/);
    assert.match(html, /disabled[^>]*>← Предыдущий/);
    assert.doesNotMatch(html, /disabled[^>]*>Следующий/);
});

test('empty rounds do not render unusable navigation', async () => {
    const html = await render(BracketRoundsList, { names, rounds: [] });
    assert.doesNotMatch(html, /Переход между раундами|Следующий|Предыдущий/);
});

for (const format of ['single_elimination', 'double_elimination', 'round_robin', 'swiss']) {
    test(`the selected match is passed through the whole ${format} view`, async () => {
        const selected = { ...fixture('chosen'), bracket: format === 'double_elimination' ? 'winners' : null };
        const html = await render(PhaseList, {
            tournament: {
                participants: Object.entries(names).map(([id, name]) => ({ id, name })),
                stages: [{ id: 'stage', phase: 1, format, rounds: [{ number: 1, fixtures: [selected] }] }],
            },
            editable: true, externalScore: true, selectedFixtureId: 'chosen',
        });
        assert.match(html, /Редактируется/);
        assert.match(html, /aria-pressed="true"/);
    });
}
