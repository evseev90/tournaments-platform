<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDayShort } from '../../lib/time';

defineProps({
    stats: { type: Object, required: true },
    matches: { type: Array, required: true },
    rivals: { type: Array, required: true },
});

const resultLabels = { win: 'победа', loss: 'поражение', draw: 'ничья' };
const resultClasses = {
    win: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    loss: 'bg-rose-50 text-rose-700 ring-rose-200',
    draw: 'bg-slate-100 text-slate-600 ring-slate-200',
};
</script>

<template>
    <Head :title="stats.name" />
    <AppLayout>
        <div class="mb-6">
            <Link href="/players" class="text-sm text-slate-400 hover:text-slate-700">← к статистике</Link>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ stats.name }}</h1>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="tile in [
                { label: 'Матчей', value: stats.matches },
                { label: 'Побед', value: stats.wins },
                { label: 'Поражений', value: stats.losses },
                { label: 'Побед, %', value: stats.winRate },
            ]" :key="tile.label" class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <div class="text-xs text-slate-500">{{ tile.label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ tile.value }}</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_minmax(0,20rem)]">
            <section class="rounded-2xl border border-slate-200 bg-white">
                <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Последние матчи</h2>
                <ul v-if="matches.length" class="divide-y divide-slate-100">
                    <li v-for="(match, index) in matches" :key="index" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3">
                        <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1" :class="resultClasses[match.result]">
                            {{ resultLabels[match.result] }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ match.opponent ?? 'без соперника' }}</span>
                        <span class="text-sm tabular-nums text-slate-500">{{ match.score }}</span>
                        <a :href="`/t/${match.tournamentSlug}`" class="w-full truncate text-xs text-slate-400 hover:text-slate-700 sm:w-auto">
                            {{ match.tournamentName }}<template v-if="match.playedAt"> · {{ formatDayShort(match.playedAt) }}</template>
                        </a>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 text-sm text-slate-400">Матчей пока нет.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white">
                <h2 class="border-b border-slate-100 px-5 py-4 font-semibold">Соперники</h2>
                <ul v-if="rivals.length" class="divide-y divide-slate-100">
                    <li v-for="rival in rivals" :key="rival.name" class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <span class="min-w-0 truncate">{{ rival.name }}</span>
                        <span class="shrink-0 tabular-nums">
                            <span class="text-emerald-700">{{ rival.wins }}</span>
                            <span class="text-slate-300"> : </span>
                            <span class="text-rose-700">{{ rival.losses }}</span>
                        </span>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 text-sm text-slate-400">Пока не с кем сравнивать.</p>
            </section>
        </div>
    </AppLayout>
</template>
