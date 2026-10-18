<script setup>
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDialogFocus } from '../lib/dialog';
import { validateSets } from '../lib/scoring';

const props = defineProps({
    fixture: { type: Object, default: null },
    names: { type: Object, required: true },
    rules: { type: Object, required: true },
    resultUrl: { type: Function, required: true },
    inline: Boolean,
    contextLabel: String,
});
const emit = defineEmits(['close', 'busy-change']);

const maxSets = computed(() => props.rules.sets_to_win * 2 - 1);
const sets = ref([]);
const error = ref(null);
const dialogElement = ref(null);

const busy = ref(false);
const wide = ref(false);
const modal = computed(() => Boolean(props.fixture) && (!props.inline || !wide.value));
function close() { if (!busy.value) emit('close'); }
useDialogFocus(dialogElement, () => modal.value, close);
let media;
let previousOverflow;
const updateWidth = () => { wide.value = media.matches; };
onMounted(() => {
    media = window.matchMedia('(min-width: 1024px)');
    updateWidth();
    media.addEventListener('change', updateWidth);
});
watch(modal, (open) => {
    if (open) {
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    } else if (previousOverflow !== undefined) {
        document.body.style.overflow = previousOverflow;
        previousOverflow = undefined;
    }
}, { immediate: true });
onBeforeUnmount(() => {
    media?.removeEventListener('change', updateWidth);
    if (previousOverflow !== undefined) document.body.style.overflow = previousOverflow;
});
watch(busy, (value) => emit('busy-change', value));

watch(() => props.fixture?.id, async () => {
    const f = props.fixture;
    error.value = null;
    const existing = f?.outcome?.type === 'sets' ? f.outcome.sets.map(([h, a]) => [h, a]) : [];
    sets.value = Array.from({ length: maxSets.value }, (_, i) => existing[i] ?? ['', '']);
    if (f && props.inline && wide.value) {
        await nextTick();
        dialogElement.value?.querySelector('[data-dialog-initial-focus]')?.focus({ preventScroll: true });
    }
}, { immediate: true });

const enteredRows = computed(() => sets.value.slice(0, sets.value.findLastIndex(([h, a]) => h !== '' || a !== '') + 1));
const completeRows = computed(() => enteredRows.value.every(([h, a]) => h !== '' && a !== ''));
const filled = computed(() => enteredRows.value.filter(([h, a]) => h !== '' && a !== '').map(([h, a]) => [Number(h), Number(a)]));

const tally = computed(() => filled.value.reduce((t, [h, a]) => {
    if (h > a) t.home++;
    else if (a > h) t.away++;
    return t;
}, { home: 0, away: 0 }));
const validResult = computed(() => completeRows.value && filled.value.length > 0 && !validateSets(props.rules, filled.value));
const winner = computed(() => validResult.value ? props.names[props.fixture?.[tally.value.home > tally.value.away ? 'homeId' : 'awayId']] : null);

const decidedAfter = computed(() => {
    let home = 0; let away = 0;
    for (const [i, [h, a]] of filled.value.entries()) {
        if (h > a) home++; else if (a > h) away++;
        if (home === props.rules.sets_to_win || away === props.rules.sets_to_win) return i;
    }
    return null;
});

function rowVisible(index) {
    if (sets.value[index]?.some((value) => value !== '')) return true;
    if (index === 0) return true;
    if (decidedAfter.value !== null && index > decidedAfter.value) return false;
    return filled.value.length >= index;
}

function submit(outcome) {
    if (busy.value || !props.fixture) return;
    busy.value = true;
    router.post(props.resultUrl(props.fixture), { outcome }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { error.value = errors.domain ?? Object.values(errors)[0]; },
        onNetworkError: () => { error.value = 'Не удалось сохранить счёт. Проверьте соединение и повторите попытку.'; return false; },
        onHttpException: () => { error.value = 'Не удалось сохранить результат. Повторите попытку.'; return false; },
        onFinish: () => { busy.value = false; },
    });
}

function saveSets() {
    if (!completeRows.value) {
        error.value = 'Укажите очки обоих участников в каждой начатой партии';
        return;
    }
    error.value = validateSets(props.rules, filled.value);
    if (error.value) return;
    submit({ type: 'sets', sets: filled.value });
}

function walkover(side) {
    if (busy.value || !props.fixture) return;
    if (confirm(`Засчитать техническую победу: ${props.names[props.fixture[`${side}Id`]]}?`)) {
        submit({ type: 'walkover', winner: side });
    }
}

function revert() {
    if (busy.value || !props.fixture) return;
    if (!confirm('Отменить результат этого матча?')) return;
    busy.value = true;
    router.delete(props.resultUrl(props.fixture), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { error.value = errors.domain ?? Object.values(errors)[0]; },
        onNetworkError: () => { error.value = 'Не удалось отменить результат. Проверьте соединение.'; return false; },
        onHttpException: () => { error.value = 'Не удалось отменить результат. Повторите попытку.'; return false; },
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
    <Teleport to="body" :disabled="inline && wide">
        <div v-if="fixture" :class="inline && wide ? 'min-w-0' : 'fixed inset-0 z-50 overflow-y-auto bg-bg px-6 py-6 sm:flex sm:items-start sm:justify-center sm:bg-board/70 sm:py-12'" @click.self="close">
            <button v-if="modal && inline" type="button" class="mb-5 min-h-11 text-sm text-ink-2 sm:hidden" :disabled="busy" @click="close">← К сетке турнира</button>
            <div ref="dialogElement" :role="modal ? 'dialog' : 'region'" :aria-modal="modal ? true : undefined" aria-labelledby="score-dialog-title" tabindex="-1" class="score-editor w-full rounded-md border border-line border-t-[3px] border-t-accent bg-surface" :class="inline && wide ? '' : 'mx-auto max-w-md'" @keydown.esc="close">
                <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-3">
                    <div class="min-w-0">
                        <span class="badge mb-4" :class="fixture.status === 'finished' ? 'bg-win-soft text-win' : 'bg-live-soft text-live'">{{ fixture.status === 'finished' ? 'Результат сохранён' : 'Ожидает результата' }}</span>
                        <p v-if="contextLabel" class="eyebrow mb-2">{{ contextLabel }}</p>
                        <h2 id="score-dialog-title" class="headline text-3xl">{{ fixture.status === 'finished' ? 'Изменить счёт' : 'Внести счёт' }}</h2>
                        <p class="mt-4 break-words text-base leading-relaxed font-semibold">{{ names[fixture.homeId] }}<br />{{ names[fixture.awayId] }}</p>
                        <p class="mt-3 text-xs leading-relaxed text-ink-2">
                            до {{ rules.sets_to_win }} побед · партия до {{ rules.points_per_set }}<template v-if="rules.points_in_deciding_set !== rules.points_per_set"> · решающая до {{ rules.points_in_deciding_set }}</template>
                        </p>
                    </div>
                    <button type="button" aria-label="Закрыть ввод счёта" :disabled="busy" class="shrink-0 rounded-sm p-2 text-ink-2 hover:bg-sunken" @click="close">✕</button>
                </div>

                <div class="space-y-3 px-6 py-4">
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2">
                        <span />
                        <span class="eyebrow truncate text-center">{{ names[fixture.homeId] }}</span>
                        <span />
                        <span class="eyebrow truncate text-center">{{ names[fixture.awayId] }}</span>
                    </div>
                    <template v-for="(set, index) in sets" :key="index">
                        <div v-if="rowVisible(index)" class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2">
                            <span class="font-mono text-xs text-ink-3">{{ index + 1 }}</span>
                            <input v-model="set[0]" type="number" min="0" step="1" :disabled="busy" inputmode="numeric" :aria-label="`Сет ${index + 1}, ${names[fixture.homeId]}`" :data-dialog-initial-focus="index === 0 ? '' : undefined" class="field text-center font-mono text-lg" />
                            <span class="text-center text-ink-3">:</span>
                            <input v-model="set[1]" type="number" min="0" step="1" :disabled="busy" inputmode="numeric" :aria-label="`Сет ${index + 1}, ${names[fixture.awayId]}`" class="field text-center font-mono text-lg" />
                        </div>
                    </template>
                    <div class="grid grid-cols-[2rem_1fr_1rem_1fr] items-center gap-2 border-t border-line pt-3">
                        <span class="eyebrow">итог</span>
                        <span class="text-center font-mono text-2xl font-semibold">{{ tally.home }}</span>
                        <span class="text-center font-mono text-2xl text-ink-3">:</span>
                        <span class="text-center font-mono text-2xl font-semibold">{{ tally.away }}</span>
                    </div>
                </div>

                <p v-if="error" role="alert" class="mx-6 rounded-sm border-l-[3px] border-danger bg-danger-soft px-4 py-3 text-sm text-danger">{{ error }}</p>
                <p v-else-if="winner" class="mx-6 rounded-sm bg-win-soft px-4 py-3 text-sm leading-relaxed text-win" aria-live="polite">Победитель: <strong>{{ winner }}</strong>. Результат появится в сетке после сохранения.</p>

                <div class="flex flex-col gap-3 px-6 pt-4 pb-6">
                    <button type="button" :disabled="busy" :aria-busy="busy" class="btn btn-primary min-h-12 w-full" @click="saveSets">{{ busy ? 'Сохраняем…' : 'Сохранить результат' }}</button>
                    <button type="button" :disabled="busy" class="btn btn-secondary min-h-11 w-full" @click="close">Отмена</button>
                    <details :key="fixture.id" class="border-t border-line pt-4">
                        <summary class="min-h-11 cursor-pointer py-3 text-sm text-ink-2 focus-visible:outline-2 focus-visible:outline-accent">Другие действия</summary>
                        <div class="mt-2 grid gap-3">
                            <p class="text-xs leading-relaxed text-ink-2">Если соперник не вышел на матч, выберите победителя. Перед сохранением попросим подтверждение.</p>
                            <button v-for="side in ['home', 'away']" :key="side" type="button" :disabled="busy" class="btn btn-secondary min-h-11 w-full justify-start whitespace-normal break-words text-left text-xs" @click="walkover(side)">
                                <span class="min-w-0">Техническая победа — {{ names[fixture[`${side}Id`]] }}</span>
                            </button>
                            <button v-if="fixture.status === 'finished'" type="button" :disabled="busy" class="btn btn-danger min-h-11 w-full" @click="revert">Отменить результат</button>
                        </div>
                    </details>
                    <p class="mt-2 text-xs leading-relaxed text-ink-2">Результат можно исправить, пока не сыгран следующий матч.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
