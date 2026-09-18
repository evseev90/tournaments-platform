<script setup>
import { computed } from 'vue';
import { roundLabel } from '../lib/labels';
import FixtureCard from './FixtureCard.vue';

const props = defineProps({
    stage: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
});
const emit = defineEmits(['open']);

const lastRound = computed(() => props.stage.rounds.length);
const thirdPlace = computed(() => props.stage.rounds[lastRound.value - 1]?.fixtures.find((f) => f.position === 1) ?? null);

function fixturesOf(round) {
    return round.number === lastRound.value ? round.fixtures.filter((f) => f.position === 0) : round.fixtures;
}
</script>

<template>
    <div class="overflow-x-auto pb-2">
        <div class="flex min-w-max gap-6">
            <div v-for="round in stage.rounds" :key="round.number" class="flex w-56 flex-col">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">{{ roundLabel(stage, round.number) }}</h3>
                <div class="flex flex-1 flex-col justify-around gap-3">
                    <FixtureCard v-for="f in fixturesOf(round)" :key="f.id" :fixture="f" :names="names" :editable="editable" @open="emit('open', $event)" />
                </div>
            </div>
        </div>

        <div v-if="thirdPlace" class="mt-6 w-56">
            <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">Матч за 3 место</h3>
            <FixtureCard :fixture="thirdPlace" :names="names" :editable="editable" @open="emit('open', $event)" />
        </div>
    </div>
</template>
