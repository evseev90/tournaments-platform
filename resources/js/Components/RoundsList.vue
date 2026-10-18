<script setup>
import { roundLabel } from '../lib/labels';
import FixtureCard from './FixtureCard.vue';
import BracketRoundsList from './BracketRoundsList.vue';
import { computed } from 'vue';

const props = defineProps({
    stage: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    selectedFixtureId: { type: String, default: null },
    /** В узкой колонке (группы рядом) карточки идут в один столбец, иначе имена не помещаются. */
    dense: Boolean,
});
const emit = defineEmits(['open']);
const mobileRounds = computed(() => props.stage.rounds.map((round) => ({ ...round, label: roundLabel(props.stage, round.number) })));
</script>

<template>
    <BracketRoundsList :rounds="mobileRounds" :names="names" :editable="editable" :selected-fixture-id="selectedFixtureId" class="sm:hidden" @open="emit('open', $event)" />
    <div class="hidden space-y-6 sm:block">
        <section v-for="round in stage.rounds" :key="round.number">
            <h3 class="eyebrow mb-3">{{ roundLabel(stage, round.number) }}</h3>
            <div class="grid items-stretch gap-3" :class="dense ? '' : 'sm:grid-cols-2 lg:grid-cols-3'">
                <FixtureCard v-for="f in round.fixtures" :key="f.id" :fixture="f" :names="names" :editable="editable" :selected="f.id === selectedFixtureId" :label="`Матч ${f.position + 1}`" @open="emit('open', $event)" />
            </div>
        </section>
    </div>
</template>
