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
    win: 'bg-win-soft text-win',
    loss: 'bg-danger-soft text-danger',
    draw: 'bg-sunken text-ink-2',
};
</script>

<template>
    <Head :title="stats.name" />
    <AppLayout>
        <div class="mb-6 border-b-2 border-ink pb-4">
            <Link href="/players" class="eyebrow transition hover:text-ink">← к статистике</Link>
            <h1 class="headline mt-2 text-4xl">{{ stats.name }}</h1>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="tile in [
                { label: 'Матчей', value: stats.matches },
                { label: 'Побед', value: stats.wins },
                { label: 'Поражений', value: stats.losses },
                { label: 'Побед, %', value: stats.winRate },
            ]" :key="tile.label" class="panel px-4 py-3">
                <div class="label">{{ tile.label }}</div>
                <div class="mt-1 font-mono text-3xl font-semibold">{{ tile.value }}</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_minmax(0,20rem)]">
            <section class="panel overflow-hidden">
                <h2 class="headline border-b border-line px-5 py-4 text-lg">Последние матчи</h2>
                <ul v-if="matches.length">
                    <li v-for="(match, index) in matches" :key="index" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3" :class="index ? 'border-t border-line' : ''">
                        <span class="badge shrink-0" :class="resultClasses[match.result]">{{ resultLabels[match.result] }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ match.opponent ?? 'без соперника' }}</span>
                        <span class="font-mono text-[13px] text-ink-2">{{ match.score }}</span>
                        <a :href="`/t/${match.tournamentSlug}`" class="w-full truncate font-mono text-[11px] text-ink-3 transition hover:text-ink sm:w-auto">
                            {{ match.tournamentName }}<template v-if="match.playedAt"> · {{ formatDayShort(match.playedAt) }}</template>
                        </a>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 text-sm text-ink-3">Матчей пока нет.</p>
            </section>

            <section class="panel overflow-hidden">
                <h2 class="headline border-b border-line px-5 py-4 text-lg">Соперники</h2>
                <ul v-if="rivals.length">
                    <li v-for="(rival, index) in rivals" :key="rival.name" class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm" :class="index ? 'border-t border-line' : ''">
                        <span class="min-w-0 truncate">{{ rival.name }}</span>
                        <span class="shrink-0 font-mono text-[13px]">
                            <span class="text-win">{{ rival.wins }}</span>
                            <span class="text-ink-3">:</span>
                            <span class="text-danger">{{ rival.losses }}</span>
                        </span>
                    </li>
                </ul>
                <p v-else class="px-5 py-6 text-sm text-ink-3">Пока не с кем сравнивать.</p>
            </section>
        </div>
    </AppLayout>
</template>
