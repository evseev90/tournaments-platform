<script setup>
import { computed } from 'vue';
import { setsWon } from '../lib/scoring';

const props = defineProps({
    fixture: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    /** В сетке карточки одной высоты, иначе линии не сойдутся: счёт по партиям уходит в подпись под карточкой. */
    inBracket: Boolean,
});
const emit = defineEmits(['open']);

const clickable = computed(() => props.editable && (props.fixture.status === 'ready' || props.fixture.status === 'finished'));

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
        class="relative flex h-full w-full flex-col rounded-sm border bg-surface text-left transition"
        :class="[
            inBracket ? '' : 'overflow-hidden',
            fixture.status === 'bye' ? 'border-dashed border-line opacity-70' : 'border-line',
            fixture.status === 'ready' && editable ? 'border-accent' : '',
            clickable ? 'cursor-pointer hover:border-accent hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent' : '',
        ]"
        @click="clickable && emit('open', fixture)"
    >
        <div
            v-for="(row, index) in rows" :key="row.side"
            class="flex flex-1 items-center gap-2 pr-3"
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
