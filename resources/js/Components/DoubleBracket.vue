<script setup>
import { computed } from 'vue';
import { doubleRoundLabel } from '../lib/labels';
import { CARD_HEIGHT, CARD_WIDTH, layoutRounds } from '../lib/bracketLayout';
import FixtureCard from './FixtureCard.vue';

const props = defineProps({
    stage: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
});
const emit = defineEmits(['open']);

/**
 * Раунды одной ветки. Стадия нумерует раунды сквозной лентой, а каждая ветка рисуется
 * со своей первой колонки, поэтому номера здесь переводятся в локальные.
 */
function roundsOf(bracket) {
    return props.stage.rounds
        .map((round) => round.fixtures.filter((f) => f.bracket === bracket))
        .filter((fixtures) => fixtures.length > 0)
        .map((fixtures, index) => ({ number: index + 1, fixtures }));
}

const branches = computed(() => ['winners', 'losers', 'grand_final']
    .map((bracket) => {
        const rounds = roundsOf(bracket);
        return {
            bracket,
            rounds,
            layout: rounds.length ? layoutRounds(rounds) : null,
            titles: rounds.map((_, index) => doubleRoundLabel(bracket, index, rounds.length)),
        };
    })
    .filter((branch) => branch.layout !== null));
</script>

<template>
    <div class="space-y-8">
        <section v-for="branch in branches" :key="branch.bracket" class="overflow-x-auto pb-2">
            <div class="min-w-max">
                <div class="relative mb-3 h-4" :style="{ width: `${branch.layout.width}px` }">
                    <h3
                        v-for="(column, index) in branch.layout.columns" :key="column.round"
                        class="absolute top-0 truncate text-xs font-medium uppercase tracking-wide text-slate-500"
                        :style="{ left: `${column.x}px`, width: `${CARD_WIDTH}px` }"
                    >
                        {{ branch.titles[index] }}
                    </h3>
                </div>

                <div class="relative" :style="{ width: `${branch.layout.width}px`, height: `${branch.layout.height}px` }">
                    <svg class="pointer-events-none absolute inset-0" :width="branch.layout.width" :height="branch.layout.height" aria-hidden="true">
                        <path
                            v-for="(edge, index) in branch.layout.edges" :key="index"
                            :d="edge.path" fill="none" stroke-width="1.5" stroke-linecap="round"
                            :class="edge.active ? 'stroke-emerald-400' : 'stroke-slate-200'"
                        />
                    </svg>

                    <div
                        v-for="item in branch.layout.fixtures" :key="item.fixture.id"
                        class="absolute"
                        :style="{ left: `${item.x}px`, top: `${item.y}px`, width: `${CARD_WIDTH}px`, height: `${CARD_HEIGHT}px` }"
                    >
                        <FixtureCard :fixture="item.fixture" :names="names" :editable="editable" in-bracket @open="emit('open', $event)" />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
