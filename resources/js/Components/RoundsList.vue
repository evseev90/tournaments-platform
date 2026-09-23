<script setup>
import { roundLabel } from '../lib/labels';
import FixtureCard from './FixtureCard.vue';

defineProps({
    stage: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    /** В узкой колонке (группы рядом) карточки идут в один столбец, иначе имена не помещаются. */
    dense: Boolean,
});
const emit = defineEmits(['open']);
</script>

<template>
    <div class="space-y-6">
        <section v-for="round in stage.rounds" :key="round.number">
            <h3 class="eyebrow mb-3">{{ roundLabel(stage, round.number) }}</h3>
            <div class="grid items-stretch gap-3" :class="dense ? '' : 'sm:grid-cols-2 lg:grid-cols-3'">
                <FixtureCard v-for="f in round.fixtures" :key="f.id" :fixture="f" :names="names" :editable="editable" @open="emit('open', $event)" />
            </div>
        </section>
    </div>
</template>
