<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    competitors: { type: Array, required: true },
    type: { type: String, default: null },
});

const tabs = [
    { value: null, label: 'Все' },
    { value: 'player', label: 'Игроки' },
    { value: 'pair', label: 'Пары' },
    { value: 'team', label: 'Команды' },
];

const hasSets = computed(() => props.competitors.some((c) => c.setsWon + c.setsLost > 0));

function select(value) {
    router.get('/players', value ? { type: value } : {}, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Статистика" />
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Статистика</h1>
            <div class="flex gap-1 rounded-xl bg-slate-100 p-1">
                <button
                    v-for="tab in tabs" :key="tab.label" type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="type === tab.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    @click="select(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <p v-if="!competitors.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400">
            Статистика копится по турнирам вашего аккаунта. Сыграйте хотя бы один матч.
        </p>

        <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Участник</th>
                        <th class="px-3 py-2 text-center font-medium" title="Турниры">Т</th>
                        <th class="px-3 py-2 text-center font-medium" title="Матчи">М</th>
                        <th class="px-3 py-2 text-center font-medium" title="Победы">В</th>
                        <th class="px-3 py-2 text-center font-medium" title="Поражения">П</th>
                        <th v-if="hasSets" class="hidden px-3 py-2 text-center font-medium sm:table-cell">Партии</th>
                        <th class="px-3 py-2 text-right font-medium">Побед, %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in competitors" :key="row.id" class="hover:bg-slate-50/60">
                        <td class="px-3 py-2">
                            <Link :href="`/players/${row.id}`" class="font-medium hover:text-emerald-700">{{ row.name }}</Link>
                        </td>
                        <td class="px-3 py-2 text-center tabular-nums text-slate-500">{{ row.tournaments }}</td>
                        <td class="px-3 py-2 text-center tabular-nums">{{ row.matches }}</td>
                        <td class="px-3 py-2 text-center tabular-nums">{{ row.wins }}</td>
                        <td class="px-3 py-2 text-center tabular-nums">{{ row.losses }}</td>
                        <td v-if="hasSets" class="hidden px-3 py-2 text-center tabular-nums text-slate-500 sm:table-cell">{{ row.setsWon }}:{{ row.setsLost }}</td>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ row.winRate }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
