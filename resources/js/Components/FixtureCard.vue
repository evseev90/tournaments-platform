<script setup>
import { computed } from 'vue';
import { setsWon } from '../lib/scoring';

const props = defineProps({
    fixture: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    compact: Boolean,
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
        class="w-full rounded-xl border bg-white text-left shadow-sm transition"
        :class="[
            fixture.status === 'bye' ? 'border-dashed border-slate-200 opacity-70' : 'border-slate-200',
            clickable ? 'cursor-pointer hover:border-emerald-400 hover:shadow' : '',
            fixture.status === 'ready' && editable ? 'ring-1 ring-emerald-100' : '',
        ]"
        @click="clickable && emit('open', fixture)"
    >
        <div v-for="(row, index) in rows" :key="row.side" class="flex items-center gap-2 px-3 py-1.5" :class="index === 0 ? 'border-b border-slate-100' : ''">
            <span class="min-w-0 flex-1 truncate text-sm" :class="row.name ? (row.winner ? 'font-semibold text-slate-900' : fixture.status === 'finished' ? 'text-slate-400' : 'text-slate-700') : 'italic text-slate-300'">
                {{ row.name ?? (fixture.status === 'bye' ? '—' : 'ожидание') }}
            </span>
            <span v-if="row.walkover" class="text-[10px] font-medium uppercase text-emerald-700">тех.</span>
            <span v-else-if="row.sets !== null || row.points !== null" class="w-5 text-right text-sm font-semibold tabular-nums" :class="row.winner ? 'text-emerald-700' : 'text-slate-400'">
                {{ row.sets ?? row.points }}
            </span>
            <span v-else-if="fixture.status === 'bye' && row.name" class="text-[10px] uppercase text-slate-400">проход</span>
        </div>
        <div v-if="setScores.length && !compact" class="border-t border-slate-100 px-3 py-1 text-[11px] tabular-nums text-slate-400">
            {{ setScores.map(([h, a]) => `${h}:${a}`).join(' · ') }}
        </div>
    </component>
</template>
