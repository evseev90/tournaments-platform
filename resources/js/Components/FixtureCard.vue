<script setup>
import { computed } from 'vue';
import { setsWon } from '../lib/scoring';

const props = defineProps({
    fixture: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    selected: Boolean,
    /** В сетке карточки одной высоты, иначе линии не сойдутся: счёт по партиям уходит в подпись под карточкой. */
    inBracket: Boolean,
    label: { type: String, default: 'Матч' },
});
const emit = defineEmits(['open']);

const clickable = computed(() => props.editable && (props.fixture.status === 'ready' || props.fixture.status === 'finished'));
const statusLabel = computed(() => ({ ready: 'К игре', finished: 'Завершён', bye: 'Проход' }[props.fixture.status] ?? 'Ожидание'));

const rows = computed(() => ['home', 'away'].map((side) => {
    const id = props.fixture[`${side}Id`];
    const outcome = props.fixture.outcome;
    return {
        side,
        id,
        name: id ? props.names[id] ?? '?' : null,
        winner: props.fixture.status === 'finished' && props.fixture.winnerId === id,
        sets: setsWon(outcome, side),
        points: outcome?.type === 'points' ? outcome[side] : null,
        walkover: outcome?.type === 'walkover' && outcome.winner === side,
    };
}));

const setScores = computed(() => props.fixture.outcome?.type === 'sets' ? props.fixture.outcome.sets : []);
</script>

<template>
    <component
        :is="clickable ? 'button' : 'div'" :type="clickable ? 'button' : undefined"
        class="relative flex h-full w-full flex-col items-stretch rounded-sm border text-left transition"
        :aria-label="clickable ? `${label}: ${rows.map((row) => row.name ?? 'ожидание').join(' — ')}. Ввести или изменить счёт` : undefined"
        :aria-pressed="clickable ? selected : undefined"
        :class="[
            inBracket ? '' : 'overflow-hidden',
            selected ? 'border-accent ring-2 ring-accent bg-accent-soft' : fixture.status === 'bye' ? 'border-dashed border-line bg-surface opacity-70' : 'border-line bg-surface',
            clickable ? 'cursor-pointer hover:border-accent hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent' : '',
        ]"
        @click="clickable && emit('open', fixture)"
    >
        <div class="flex w-full shrink-0 items-center justify-between gap-2 border-b border-line bg-sunken px-3 py-2 text-[10px]">
            <span class="min-w-0 truncate font-display tracking-wider uppercase">{{ label }}</span>
            <span class="shrink-0 font-mono" :class="selected ? 'text-accent' : fixture.status === 'finished' ? 'text-win' : 'text-ink-2'">{{ selected ? 'Редактируется' : statusLabel }}</span>
        </div>
        <div
            v-for="(row, index) in rows" :key="row.side"
            class="flex w-full flex-1 items-center gap-2 pr-3"
            :class="index === 0 ? 'border-b border-line' : ''"
        >
            <!-- Победителя держит грань слева: она читается быстрее, чем начертание. -->
            <span class="w-[3px] shrink-0 self-stretch" :class="row.winner ? 'bg-win' : ''" />
            <span
                class="min-w-0 flex-1 truncate py-1.5 text-sm leading-5"
                :class="row.name
                    ? (row.winner ? 'font-semibold text-ink' : fixture.status === 'finished' ? 'text-ink-2' : 'text-ink')
                    : 'italic text-ink-3'"
            >
                {{ row.name ?? (fixture.status === 'bye' ? '—' : 'ожидание') }}
            </span>
            <span v-if="row.walkover" class="eyebrow shrink-0 text-win">тех.</span>
            <span
                v-else-if="row.sets !== null || row.points !== null"
                class="shrink-0 font-mono text-[17px] font-semibold leading-none"
                :class="row.winner ? 'text-win' : 'text-ink-2'"
            >
                {{ row.sets ?? row.points }}
            </span>
            <span v-else-if="fixture.status === 'bye' && row.name" class="eyebrow shrink-0">проход</span>
        </div>

        <div
            v-if="setScores.length"
            class="px-3 font-mono text-[11px] text-ink-3"
            :class="inBracket ? 'absolute inset-x-0 top-full truncate pt-1' : 'border-t border-line py-1'"
        >
            {{ setScores.map(([h, a]) => `${h}:${a}`).join(' · ') }}
        </div>
    </component>
</template>
