<script setup>
import { computed } from 'vue';

const props = defineProps({
    standings: { type: Array, required: true },
    names: { type: Object, required: true },
});

const hasDraws = computed(() => props.standings.some((r) => r.draws > 0));
const hasSets = computed(() => props.standings.some((r) => r.setsWon + r.setsLost > 0));
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">#</th>
                    <th class="px-3 py-2 text-left font-medium">Участник</th>
                    <th class="px-3 py-2 text-center font-medium" title="Игры">И</th>
                    <th class="px-3 py-2 text-center font-medium" title="Победы">В</th>
                    <th v-if="hasDraws" class="px-3 py-2 text-center font-medium" title="Ничьи">Н</th>
                    <th class="px-3 py-2 text-center font-medium" title="Поражения">П</th>
                    <th v-if="hasSets" class="px-3 py-2 text-center font-medium">Партии</th>
                    <th v-if="hasSets" class="hidden px-3 py-2 text-center font-medium sm:table-cell">Очки в партиях</th>
                    <th class="px-3 py-2 text-center font-medium" title="Очки">О</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="row in standings" :key="row.participantId" :class="row.rank <= 3 && row.played > 0 ? 'bg-emerald-50/40' : ''">
                    <td class="px-3 py-2 tabular-nums text-slate-400">{{ row.rank }}</td>
                    <td class="px-3 py-2 font-medium">{{ names[row.participantId] ?? '?' }}</td>
                    <td class="px-3 py-2 text-center tabular-nums">{{ row.played }}</td>
                    <td class="px-3 py-2 text-center tabular-nums">{{ row.wins }}</td>
                    <td v-if="hasDraws" class="px-3 py-2 text-center tabular-nums">{{ row.draws }}</td>
                    <td class="px-3 py-2 text-center tabular-nums">{{ row.losses }}</td>
                    <td v-if="hasSets" class="px-3 py-2 text-center tabular-nums text-slate-500">{{ row.setsWon }}:{{ row.setsLost }}</td>
                    <td v-if="hasSets" class="hidden px-3 py-2 text-center tabular-nums text-slate-500 sm:table-cell">{{ row.pointsWon }}:{{ row.pointsLost }}</td>
                    <td class="px-3 py-2 text-center font-semibold tabular-nums">{{ row.score }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
