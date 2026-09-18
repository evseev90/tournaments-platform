<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
        <div v-if="fixture" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" @click.self="emit('close')">
            <div class="w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ names[fixture.homeId] }} <span class="text-slate-400">—</span> {{ names[fixture.awayId] }}</h2>
                        <p class="text-xs text-slate-400">До {{ rules.sets_to_win }} побед в партиях, партия до {{ rules.points_per_set }}<template v-if="rules.points_in_deciding_set !== rules.points_per_set">, решающая до {{ rules.points_in_deciding_set }}</template></p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="emit('close')">✕</button>
                </div>

                <div class="space-y-2">
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2 text-xs text-slate-400">
                        <span />
                        <span class="truncate text-center">{{ names[fixture.homeId] }}</span>
                        <span />
                        <span class="truncate text-center">{{ names[fixture.awayId] }}</span>
                    </div>
                    <template v-for="(set, index) in sets" :key="index">
                        <div v-if="rowVisible(index)" class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2">
                            <span class="text-xs text-slate-400">{{ index + 1 }}</span>
                            <input v-model="set[0]" type="number" min="0" inputmode="numeric" class="rounded-lg border border-slate-200 px-2 py-2 text-center text-lg tabular-nums focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                            <span class="text-center text-slate-300">:</span>
                            <input v-model="set[1]" type="number" min="0" inputmode="numeric" class="rounded-lg border border-slate-200 px-2 py-2 text-center text-lg tabular-nums focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                        </div>
                    </template>
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2 text-sm font-semibold">
                        <span />
                        <span class="text-center">{{ tally.home }}</span>
                        <span class="text-center text-slate-300">:</span>
                        <span class="text-center">{{ tally.away }}</span>
                    </div>
                </div>

                <p v-if="error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>

                <div class="mt-5 flex flex-col gap-2">
                    <button type="button" :disabled="busy" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40" @click="saveSets">
                        Сохранить счёт
                    </button>
                    <div class="flex gap-2 text-xs">
                        <button type="button" :disabled="busy" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-slate-600 hover:bg-slate-50" @click="walkover('home')">Тех. победа слева</button>
                        <button type="button" :disabled="busy" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-slate-600 hover:bg-slate-50" @click="walkover('away')">Тех. победа справа</button>
                    </div>
                    <button v-if="fixture.status === 'finished'" type="button" :disabled="busy" class="text-xs text-rose-600 hover:underline" @click="revert">
                        Отменить результат
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
