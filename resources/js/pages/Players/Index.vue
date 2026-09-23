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
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b-2 border-ink pb-4">
            <h1 class="headline text-4xl">Статистика</h1>
            <div class="seg" role="tablist">
                <button
                    v-for="tab in tabs" :key="tab.label" type="button" role="tab"
                    class="seg-btn" :aria-selected="type === tab.value"
                    @click="select(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <p v-if="!competitors.length" class="rounded-sm border border-dashed border-line px-5 py-12 text-center text-sm text-ink-3">
            Статистика копится по турнирам вашего аккаунта. Сыграйте хотя бы один матч.
        </p>

        <div v-else class="panel overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="label py-2 pr-3 text-left">Участник</th>
                        <th class="label px-3 py-2 text-right" title="Турниры">Т</th>
                        <th class="label px-3 py-2 text-right" title="Матчи">М</th>
                        <th class="label px-3 py-2 text-right" title="Победы">В</th>
                        <th class="label px-3 py-2 text-right" title="Поражения">П</th>
                        <th v-if="hasSets" class="label hidden px-3 py-2 text-right sm:table-cell">Партии</th>
                        <th class="label py-2 pl-3 text-right">Побед, %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in competitors" :key="row.id" class="border-b border-line transition hover:bg-sunken">
                        <td class="py-2.5 pr-3">
                            <Link :href="`/players/${row.id}`" class="font-medium transition hover:text-accent">{{ row.name }}</Link>
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-3">{{ row.tournaments }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.matches }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-[13px] text-win">{{ row.wins }}</td>
                        <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.losses }}</td>
                        <td v-if="hasSets" class="hidden px-3 py-2.5 text-right font-mono text-[13px] text-ink-3 sm:table-cell">{{ row.setsWon }}:{{ row.setsLost }}</td>
                        <td class="py-2.5 pl-3 text-right font-mono text-[15px] font-semibold">{{ row.winRate }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
