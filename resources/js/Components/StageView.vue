<script setup>
import { computed, ref } from 'vue';
import { formatLabels } from '../lib/labels';
import Bracket from './Bracket.vue';
import RoundsList from './RoundsList.vue';
import ScoreDialog from './ScoreDialog.vue';
import StandingsTable from './StandingsTable.vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    stage: { type: Object, required: true },
    editable: Boolean,
    resultUrl: { type: Function, default: () => '' },
});

const names = computed(() => Object.fromEntries(props.tournament.participants.map((p) => [p.id, p.name])));
const opened = ref(null);
const total = computed(() => props.stage.rounds.flatMap((r) => r.fixtures).filter((f) => f.status !== 'bye'));
const done = computed(() => total.value.filter((f) => f.status === 'finished').length);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">{{ formatLabels[stage.format] }}</h2>
            <span class="text-sm text-slate-500">Сыграно {{ done }} из {{ total.length }}</span>
        </div>

        <StandingsTable v-if="stage.standings" :standings="stage.standings" :names="names" />

        <Bracket v-if="stage.format === 'single_elimination'" :stage="stage" :names="names" :editable="editable" @open="opened = $event" />
        <RoundsList v-else :stage="stage" :names="names" :editable="editable" @open="opened = $event" />

        <ScoreDialog v-if="editable" :fixture="opened" :names="names" :rules="tournament.rules" :result-url="resultUrl" @close="opened = null" />
    </div>
</template>
