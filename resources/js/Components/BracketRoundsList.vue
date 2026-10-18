<script setup>
import FixtureCard from './FixtureCard.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    rounds: { type: Array, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
    selectedFixtureId: { type: String, default: null },
});

const emit = defineEmits(['open']);
const selected = ref(null);
watch(() => props.rounds.map((round) => round.number), (numbers) => {
    if (!numbers.includes(selected.value)) {
        selected.value = props.rounds.find((round) => round.fixtures.some((f) => f.status === 'ready'))?.number ?? numbers[0];
    }
}, { immediate: true });
const current = computed(() => props.rounds.find((round) => round.number === selected.value));
const currentIndex = computed(() => props.rounds.findIndex((round) => round.number === selected.value));
const matches = computed(() => current.value?.fixtures.filter((fixture) => fixture.status !== 'bye') ?? []);
const finished = computed(() => matches.value.filter((fixture) => fixture.status === 'finished').length);
function moveRound(offset) {
    const round = props.rounds[currentIndex.value + offset];
    if (round) selected.value = round.number;
}
watch(() => props.selectedFixtureId, (id) => {
    const round = props.rounds.find((item) => item.fixtures.some((fixture) => fixture.id === id));
    if (round) selected.value = round.number;
}, { immediate: true });
</script>

<template>
    <div class="space-y-6">
        <label v-if="rounds.length" class="block">
            <span class="label mb-2 block">Раунд</span>
            <select v-model="selected" class="field min-h-12" aria-label="Выбрать раунд">
                <option v-for="round in rounds" :key="round.number" :value="round.number">{{ round.label }}</option>
            </select>
        </label>
        <nav v-if="current" class="grid grid-cols-2 gap-2" aria-label="Переход между раундами">
            <button type="button" class="btn btn-secondary min-h-11 text-xs" :disabled="currentIndex <= 0" @click="moveRound(-1)">← Предыдущий</button>
            <button type="button" class="btn btn-secondary min-h-11 text-xs" :disabled="currentIndex >= rounds.length - 1" @click="moveRound(1)">Следующий →</button>
        </nav>
        <section v-if="current" :key="current.number">
            <h3 class="eyebrow mb-3">{{ current.label }}</h3>
            <p class="mb-4 text-xs text-ink-2" role="status">Раунд {{ currentIndex + 1 }} из {{ rounds.length }} · Сыграно {{ finished }} из {{ matches.length }} матчей</p>
            <div class="grid gap-3">
                <div v-for="fixture in current.fixtures" :key="fixture.id" class="min-h-20">
                    <FixtureCard :fixture="fixture" :names="names" :editable="editable" :selected="fixture.id === selectedFixtureId" :label="`Матч ${fixture.position + 1}`" @open="emit('open', $event)" />
                </div>
            </div>
        </section>
    </div>
</template>
