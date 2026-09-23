<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useDialogFocus } from '../lib/dialog';
import { validateSets } from '../lib/scoring';

const props = defineProps({
    fixture: { type: Object, default: null },
    names: { type: Object, required: true },
    rules: { type: Object, required: true },
    resultUrl: { type: Function, required: true },
});
const emit = defineEmits(['close']);

const maxSets = computed(() => props.rules.sets_to_win * 2 - 1);
const sets = ref([]);
const error = ref(null);
const dialogElement = ref(null);

useDialogFocus(dialogElement, () => Boolean(props.fixture), () => emit('close'));
const busy = ref(false);

watch(() => props.fixture, (f) => {
    error.value = null;
    const existing = f?.outcome?.type === 'sets' ? f.outcome.sets.map(([h, a]) => [h, a]) : [];
    sets.value = Array.from({ length: maxSets.value }, (_, i) => existing[i] ?? ['', '']);
}, { immediate: true });

const filled = computed(() => sets.value.filter(([h, a]) => h !== '' && a !== '').map(([h, a]) => [Number(h), Number(a)]));

const tally = computed(() => filled.value.reduce((t, [h, a]) => (h > a ? t.home++ : t.away++, t), { home: 0, away: 0 }));

const decidedAfter = computed(() => {
    let home = 0; let away = 0;
    for (const [i, [h, a]] of filled.value.entries()) {
        if (h > a) home++; else away++;
        if (home === props.rules.sets_to_win || away === props.rules.sets_to_win) return i;
    }
    return null;
});

function rowVisible(index) {
    if (index === 0) return true;
    if (decidedAfter.value !== null && index > decidedAfter.value) return false;
    return filled.value.length >= index;
}

function submit(outcome) {
    busy.value = true;
    router.post(props.resultUrl(props.fixture), { outcome }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { error.value = errors.domain ?? Object.values(errors)[0]; },
        onFinish: () => { busy.value = false; },
    });
}

function saveSets() {
    error.value = validateSets(props.rules, filled.value);
    if (error.value) return;
    submit({ type: 'sets', sets: filled.value });
}

function walkover(side) {
    if (confirm(`Засчитать техническую победу: ${props.names[props.fixture[`${side}Id`]]}?`)) {
        submit({ type: 'walkover', winner: side });
    }
}

function revert() {
    if (!confirm('Отменить результат этого матча?')) return;
    busy.value = true;
    router.delete(props.resultUrl(props.fixture), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { error.value = errors.domain; },
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="fixture" class="fixed inset-0 z-50 flex items-end justify-center bg-board/70 p-0 sm:items-center sm:p-4" @click.self="emit('close')">
            <div ref="dialogElement" role="dialog" aria-modal="true" aria-labelledby="score-dialog-title" tabindex="-1" class="w-full max-w-md overflow-hidden rounded-t-md border border-line bg-surface sm:rounded-md">
                <div class="flex items-start justify-between gap-3 bg-board px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="score-dialog-title" class="headline truncate text-lg text-board-ink">{{ names[fixture.homeId] }} — {{ names[fixture.awayId] }}</h2>
                        <p class="mt-1 font-mono text-[11px] text-board-dim">
                            до {{ rules.sets_to_win }} побед · партия до {{ rules.points_per_set }}<template v-if="rules.points_in_deciding_set !== rules.points_per_set"> · решающая до {{ rules.points_in_deciding_set }}</template>
                        </p>
                    </div>
                    <button type="button" aria-label="Закрыть диалог" class="shrink-0 text-board-dim transition hover:text-board-ink" @click="emit('close')">✕</button>
                </div>

                <div class="space-y-2 px-5 py-4">
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2">
                        <span />
                        <span class="eyebrow truncate text-center">{{ names[fixture.homeId] }}</span>
                        <span />
                        <span class="eyebrow truncate text-center">{{ names[fixture.awayId] }}</span>
                    </div>
                    <template v-for="(set, index) in sets" :key="index">
                        <div v-if="rowVisible(index)" class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2">
                            <span class="font-mono text-xs text-ink-3">{{ index + 1 }}</span>
                            <input v-model="set[0]" type="number" min="0" inputmode="numeric" :aria-label="`Сет ${index + 1}, ${names[fixture.homeId]}`" :data-dialog-initial-focus="index === 0 ? '' : undefined" class="field text-center font-mono text-lg" />
                            <span class="text-center text-ink-3">:</span>
                            <input v-model="set[1]" type="number" min="0" inputmode="numeric" :aria-label="`Сет ${index + 1}, ${names[fixture.awayId]}`" class="field text-center font-mono text-lg" />
                        </div>
                    </template>
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2 border-t border-line pt-3">
                        <span class="eyebrow">итог</span>
                        <span class="text-center font-mono text-2xl font-semibold">{{ tally.home }}</span>
                        <span class="text-center font-mono text-2xl text-ink-3">:</span>
                        <span class="text-center font-mono text-2xl font-semibold">{{ tally.away }}</span>
                    </div>
                </div>

                <p v-if="error" role="alert" class="mx-5 rounded-sm border-l-[3px] border-danger bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

                <div class="flex flex-col gap-2 px-5 pt-4 pb-5">
                    <button type="button" :disabled="busy" :aria-busy="busy" class="btn btn-primary w-full" @click="saveSets">{{ busy ? 'Сохраняем…' : 'Сохранить счёт' }}</button>
                    <div class="flex gap-2">
                        <button type="button" :disabled="busy" class="btn btn-secondary btn-sm flex-1" @click="walkover('home')">Тех. слева</button>
                        <button type="button" :disabled="busy" class="btn btn-secondary btn-sm flex-1" @click="walkover('away')">Тех. справа</button>
                    </div>
                    <button v-if="fixture.status === 'finished'" type="button" :disabled="busy" class="btn btn-danger btn-sm w-full" @click="revert">
                        Отменить результат
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
