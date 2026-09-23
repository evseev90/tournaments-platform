<script setup>
import { computed } from 'vue';
import StageView from './StageView.vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    editable: Boolean,
    resultUrl: { type: Function, default: () => '' },
    pairUrl: { type: Function, default: () => '' },
});

/** Этапы турнира: группы идут одним этапом, плей-офф следующим. */
const phases = computed(() => {
    const byPhase = new Map();
    for (const stage of props.tournament.stages) {
        if (!byPhase.has(stage.phase)) byPhase.set(stage.phase, []);
        byPhase.get(stage.phase).push(stage);
    }
    return [...byPhase.entries()]
        .sort(([a], [b]) => a - b)
        .map(([phase, stages]) => ({
            phase,
            stages,
            title: stages.length > 1 ? 'Групповой этап' : null,
        }));
});
</script>

<template>
    <div class="space-y-10">
        <section v-for="group in phases" :key="group.phase" class="space-y-6">
            <h2 v-if="group.title" class="headline border-b-2 border-ink pb-2 text-xl">{{ group.title }}</h2>

            <div :class="group.stages.length > 1 ? 'grid gap-6 lg:grid-cols-2' : ''">
                <div
                    v-for="stage in group.stages" :key="stage.id"
                    :class="group.stages.length > 1 ? 'rounded-sm border border-line p-4' : ''"
                >
                    <StageView
                        :tournament="tournament" :stage="stage" :dense="group.stages.length > 1"
                        :editable="editable" :result-url="(fixture) => resultUrl(stage, fixture)" :pair-url="pairUrl(stage)"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
