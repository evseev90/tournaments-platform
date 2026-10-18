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
        <div class="page-heading page-heading-actions">
            <div>
                <span class="eyebrow">Статистика</span>
                <h1 class="headline mt-2 text-4xl sm:text-5xl">Каждая игра в зачёт</h1>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-ink-2">Результаты участников из твоих турниров. Открой игрока, чтобы увидеть историю встреч.</p>
            </div>
            <span class="landing-count">{{ competitors.length }} профилей</span>
        </div>

        <div class="stats-toolbar">
            <div class="seg" role="tablist" aria-label="Тип участника">
                <button
                    v-for="tab in tabs" :key="tab.label" type="button" role="tab"
                    class="seg-btn" :aria-selected="type === tab.value"
                    @click="select(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <section v-if="!competitors.length" class="empty-state">
            <span class="empty-state-mark" aria-hidden="true">%</span>
            <span class="eyebrow">Здесь будут цифры</span>
            <h2 class="headline mt-2 text-2xl">Сначала сыграйте матч</h2>
            <p class="mt-2 max-w-md text-sm leading-relaxed text-ink-2">Статистика игроков появится после завершённых матчей в турнирах, сохранённых в аккаунте.</p>
            <Link href="/my" class="btn btn-secondary mt-5">Мои турниры <span aria-hidden="true">↗</span></Link>
        </section>

        <template v-else>
            <ul class="player-card-list">
                <li v-for="row in competitors" :key="row.id">
                    <Link :href="`/players/${row.id}`" class="player-card">
                        <div class="player-card-main">
                            <span class="player-avatar" aria-hidden="true">{{ row.name.slice(0, 1) }}</span>
                            <span class="min-w-0 flex-1">
                                <strong class="block truncate font-display text-lg font-medium uppercase tracking-[0.04em]">{{ row.name }}</strong>
                                <span class="mt-1 block font-mono text-[11px] text-ink-3">{{ row.matches }} матчей · {{ row.tournaments }} турниров</span>
                            </span>
                            <span class="player-card-rate"><strong>{{ row.winRate }}%</strong><span>побед</span></span>
                        </div>
                        <div class="player-card-record"><span class="text-win">{{ row.wins }} побед</span><span class="text-ink-3">/</span><span>{{ row.losses }} поражений</span></div>
                    </Link>
                </li>
            </ul>

            <div class="stats-table panel">
                <div class="stats-table-title">
                    <h2 class="headline text-lg">Общий рейтинг</h2>
                    <span class="eyebrow">{{ competitors.length }} участников</span>
                </div>
                <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink">
                        <th class="label py-2 pr-3 text-left">Участник</th>
                        <th class="label px-3 py-2 text-right">Турниры</th>
                        <th class="label px-3 py-2 text-right">Матчи</th>
                        <th class="label px-3 py-2 text-right">Победы</th>
                        <th class="label px-3 py-2 text-right">Поражения</th>
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
                        <td class="py-2.5 pl-3 text-right font-mono text-[15px] font-semibold">{{ row.winRate }}%</td>
                    </tr>
                </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
