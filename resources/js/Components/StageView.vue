<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatLabels } from '../lib/labels';
import Bracket from './Bracket.vue';
import DoubleBracket from './DoubleBracket.vue';
import RoundsList from './RoundsList.vue';
import ScoreDialog from './ScoreDialog.vue';
import StandingsTable from './StandingsTable.vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    stage: { type: Object, required: true },
    editable: Boolean,
    dense: Boolean,
    resultUrl: { type: Function, default: () => '' },
    pairUrl: { type: String, default: '' },
});

const names = computed(() => Object.fromEntries(props.tournament.participants.map((p) => [p.id, p.name])));
const opened = ref(null);
const total = computed(() => props.stage.rounds.flatMap((r) => r.fixtures).filter((f) => f.status !== 'bye'));
const done = computed(() => total.value.filter((f) => f.status === 'finished').length);
const isSwiss = computed(() => props.stage.format === 'swiss');

function pairNextRound() {
    router.post(props.pairUrl, {}, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">{{ stage.name ?? formatLabels[stage.format] }}</h2>
            <span class="text-sm text-slate-500">
                <template v-if="isSwiss">Тур {{ stage.rounds.length }} из {{ stage.plannedRounds }} · </template>
                Сыграно {{ done }} из {{ total.length }}
            </span>
        </div>

        <StandingsTable v-if="stage.standings" :standings="stage.standings" :names="names" :show-buchholz="isSwiss" />

        <div v-if="editable && stage.awaitsNextRound && pairUrl" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/50 px-4 py-3">
            <span class="text-sm text-slate-600">Тур сыгран. Соперники следующего тура подбираются по набранным очкам.</span>
            <button type="button" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" @click="pairNextRound">
                Составить тур {{ stage.rounds.length + 1 }}
            </button>
        </div>

        <DoubleBracket v-if="stage.format === 'double_elimination'" :stage="stage" :names="names" :editable="editable" @open="opened = $event" />
        <Bracket v-else-if="stage.format === 'single_elimination'" :stage="stage" :names="names" :editable="editable" @open="opened = $event" />
        <RoundsList v-else :stage="stage" :names="names" :editable="editable" :dense="dense" @open="opened = $event" />

        <ScoreDialog v-if="editable" :fixture="opened" :names="names" :rules="tournament.rules" :result-url="resultUrl" @close="opened = null" />
    </div>
</template>
