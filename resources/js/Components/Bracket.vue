<script setup>
import { computed } from 'vue';
import { roundLabel } from '../lib/labels';
import { CARD_HEIGHT, CARD_WIDTH, THIRD_PLACE_GAP, layoutBracket } from '../lib/bracketLayout';
import FixtureCard from './FixtureCard.vue';
import BracketRoundsList from './BracketRoundsList.vue';

const props = defineProps({
    stage: { type: Object, required: true },
    names: { type: Object, required: true },
    editable: Boolean,
});
const emit = defineEmits(['open']);

const layout = computed(() => layoutBracket(props.stage));
const mobileRounds = computed(() => {
    const rounds = props.stage.rounds.map((round, index) => ({
        number: round.number,
        label: roundLabel(props.stage, round.number),
        fixtures: index === props.stage.rounds.length - 1
            ? round.fixtures.filter((fixture) => fixture.position === 0)
            : round.fixtures,
    }));
    if (layout.value.thirdPlace) {
        rounds.push({ number: 'third-place', label: 'Матч за 3 место', fixtures: [layout.value.thirdPlace.fixture] });
    }
    return rounds.filter((round) => round.fixtures.length);
});
</script>

<template>
    <BracketRoundsList :rounds="mobileRounds" :names="names" :editable="editable" class="sm:hidden" @open="emit('open', $event)" />
    <div class="hidden overflow-x-auto pb-2 sm:block">
        <div class="min-w-max">
            <div class="relative mb-3 h-4" :style="{ width: `${layout.width}px` }">
                <h3
                    v-for="column in layout.columns" :key="column.round"
                    class="eyebrow absolute top-0"
                    :style="{ left: `${column.x}px`, width: `${CARD_WIDTH}px` }"
                >
                    {{ roundLabel(stage, column.round) }}
                </h3>
            </div>

            <div class="relative" :style="{ width: `${layout.width}px`, height: `${layout.height}px` }">
                <svg class="pointer-events-none absolute inset-0" :width="layout.width" :height="layout.height" aria-hidden="true">
                    <path
                        v-for="(edge, index) in layout.edges" :key="index"
                        :d="edge.path" fill="none" stroke-width="1.5" stroke-linecap="round"
                        :class="edge.active ? 'stroke-win' : 'stroke-line'"
                    />
                </svg>

                <div
                    v-for="item in layout.fixtures" :key="item.fixture.id"
                    class="absolute"
                    :style="{ left: `${item.x}px`, top: `${item.y}px`, width: `${CARD_WIDTH}px`, height: `${CARD_HEIGHT}px` }"
                >
                    <FixtureCard :fixture="item.fixture" :names="names" :editable="editable" in-bracket @open="emit('open', $event)" />
                </div>

                <template v-if="layout.thirdPlace">
                    <h3
                        class="eyebrow absolute"
                        :style="{ left: `${layout.thirdPlace.x}px`, top: `${layout.thirdPlace.y - THIRD_PLACE_GAP + 22}px`, width: `${CARD_WIDTH}px` }"
                    >
                        Матч за 3 место
                    </h3>
                    <div
                        class="absolute"
                        :style="{ left: `${layout.thirdPlace.x}px`, top: `${layout.thirdPlace.y}px`, width: `${CARD_WIDTH}px`, height: `${CARD_HEIGHT}px` }"
                    >
                        <FixtureCard :fixture="layout.thirdPlace.fixture" :names="names" :editable="editable" in-bracket @open="emit('open', $event)" />
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
