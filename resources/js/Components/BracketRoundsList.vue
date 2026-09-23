<script setup>
import FixtureCard from './FixtureCard.vue';

defineProps({
    rounds: { type: Array, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
});

const emit = defineEmits(['open']);
</script>

<template>
    <div class="space-y-6">
        <section v-for="(round, index) in rounds" :key="round.number ?? index">
            <h3 class="eyebrow mb-3 border-b border-line pb-2">{{ round.label }}</h3>
            <div class="grid gap-3">
                <div v-for="fixture in round.fixtures" :key="fixture.id" class="min-h-16">
                    <FixtureCard :fixture="fixture" :names="names" :editable="editable" @open="emit('open', $event)" />
                </div>
            </div>
        </section>
    </div>
</template>
