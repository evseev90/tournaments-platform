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
const pairing = ref(false);
const total = computed(() => props.stage.rounds.flatMap((r) => r.fixtures).filter((f) => f.status !== 'bye'));
const done = computed(() => total.value.filter((f) => f.status === 'finished').length);
const isSwiss = computed(() => props.stage.format === 'swiss');

function pairNextRound() {
    pairing.value = true;
    router.post(props.pairUrl, {}, {
        preserveScroll: true,
        onFinish: () => { pairing.value = false; },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-2 border-b border-line pb-2">
            <h2 class="headline text-lg">{{ stage.name ?? formatLabels[stage.format] }}</h2>
            <span class="font-mono text-xs text-ink-3">
                <template v-if="isSwiss">тур {{ stage.rounds.length }}/{{ stage.plannedRounds }} · </template>
                сыграно {{ done }}/{{ total.length }}
            </span>
        </div>

        <StandingsTable v-if="stage.standings" :standings="stage.standings" :names="names" :show-buchholz="isSwiss" />

        <div
            v-if="editable && stage.awaitsNextRound && pairUrl"
            class="flex flex-wrap items-center justify-between gap-3 rounded-sm border-l-[3px] border-accent bg-accent-soft px-4 py-3"
        >
            <span class="text-sm text-ink-2">Тур сыгран. Соперники следующего тура подбираются по набранным очкам.</span>
            <button type="button" class="btn btn-primary btn-sm" :disabled="pairing" :aria-busy="pairing" @click="pairNextRound">
                {{ pairing ? 'Составляем тур…' : `Составить тур ${stage.rounds.length + 1}` }}
            </button>
        </div>

        <DoubleBracket v-if="stage.format === 'double_elimination'" :stage="stage" :names="names" :editable="editable" @open="opened = $event" />
        <Bracket v-else-if="stage.format === 'single_elimination'" :stage="stage" :names="names" :editable="editable" @open="opened = $event" />
        <RoundsList v-else :stage="stage" :names="names" :editable="editable" :dense="dense" @open="opened = $event" />

        <ScoreDialog v-if="editable" :fixture="opened" :names="names" :rules="tournament.rules" :result-url="resultUrl" @close="opened = null" />
    </div>
</template>
