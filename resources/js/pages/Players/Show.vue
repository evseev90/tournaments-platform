<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDayShort } from '../../lib/time';

const props = defineProps({
    stats: { type: Object, required: true },
    matches: { type: Array, required: true },
    rivals: { type: Array, required: true },
});

const initials = computed(() => props.stats.name.split(/\s+/).map((part) => part.slice(0, 1)).slice(0, 2).join('').toUpperCase());

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
        <Link href="/players" class="eyebrow mb-5 inline-flex items-center gap-2 transition hover:text-accent"><span aria-hidden="true">←</span> К статистике</Link>

        <section class="player-profile-hero">
            <span class="player-avatar player-avatar-large" aria-hidden="true">{{ initials }}</span>
            <div class="min-w-0 flex-1">
                <span class="eyebrow">Профиль участника</span>
                <h1 class="headline mt-2 break-words text-4xl sm:text-5xl">{{ stats.name }}</h1>
                <p class="mt-2 font-mono text-[11px] text-ink-3">{{ stats.matches }} сыграно · {{ stats.wins }} побед · {{ stats.losses }} поражений</p>
            </div>
            <div class="player-profile-rate"><strong>{{ stats.winRate }}%</strong><span>побед</span></div>
        </section>

        <div class="profile-stats-grid">
            <div v-for="tile in [
                { label: 'Матчей', value: stats.matches },
                { label: 'Побед', value: stats.wins },
                { label: 'Поражений', value: stats.losses },
                { label: 'Ничьих', value: Math.max(0, stats.matches - stats.wins - stats.losses) },
            ]" :key="tile.label" class="profile-stat-tile">
                <div class="label">{{ tile.label }}</div>
                <div class="mt-1 font-display text-3xl font-semibold tabular-nums">{{ tile.value }}</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_minmax(0,20rem)]">
            <section class="profile-list-panel">
                <div class="profile-list-heading"><div><span class="eyebrow">История</span><h2 class="headline mt-1 text-xl">Последние матчи</h2></div><span class="landing-count">{{ matches.length }}</span></div>
                <ul v-if="matches.length">
                    <li v-for="(match, index) in matches" :key="index" class="profile-match-row" :class="index ? 'border-t border-line' : ''">
                        <span class="badge shrink-0" :class="resultClasses[match.result]">{{ resultLabels[match.result] }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ match.opponent ?? 'без соперника' }}</span>
                        <span class="font-mono text-[13px] text-ink-2">{{ match.score }}</span>
                        <a :href="`/t/${match.tournamentSlug}`" class="profile-match-tournament">
                            {{ match.tournamentName }}<template v-if="match.playedAt"> · {{ formatDayShort(match.playedAt) }}</template>
                        </a>
                    </li>
                </ul>
                <p v-else class="profile-empty">Матчей пока нет. Результаты завершённых матчей появятся здесь.</p>
            </section>

            <section class="profile-list-panel">
                <div class="profile-list-heading"><div><span class="eyebrow">Личные встречи</span><h2 class="headline mt-1 text-xl">Соперники</h2></div><span class="landing-count">{{ rivals.length }}</span></div>
                <ul v-if="rivals.length">
                    <li v-for="(rival, index) in rivals" :key="rival.name" class="profile-rival-row" :class="index ? 'border-t border-line' : ''">
                        <span class="min-w-0 truncate">{{ rival.name }}</span>
                        <span class="shrink-0 font-mono text-[13px]">
                            <span class="text-win">{{ rival.wins }}</span>
                            <span class="text-ink-3">:</span>
                            <span class="text-danger">{{ rival.losses }}</span>
                        </span>
                    </li>
                </ul>
                <p v-else class="profile-empty">Сыграй несколько матчей, чтобы увидеть личные встречи.</p>
            </section>
        </div>
    </AppLayout>
</template>
