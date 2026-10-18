<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDayShort } from '../../lib/time';
import { pluralize } from '../../lib/labels';

const props = defineProps({ tournaments: { type: Array, required: true } });

const grouped = computed(() => ({
    active: props.tournaments.filter((t) => t.status !== 'finished'),
    finished: props.tournaments.filter((t) => t.status === 'finished'),
}));
const totalParticipants = computed(() => props.tournaments.reduce((total, tournament) => total + tournament.participants, 0));
</script>

<template>
    <Head title="Мои турниры" />
    <AppLayout>
        <div class="page-heading page-heading-actions">
            <div>
                <span class="eyebrow">Личный кабинет</span>
                <h1 class="headline mt-2 text-4xl sm:text-5xl">Мои турниры</h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-ink-2">Всё готово к следующей игре. Продолжай турнир или собирай новый.</p>
            </div>
            <div class="flex w-full gap-2 sm:w-auto">
                <Link href="/" class="btn btn-primary min-h-11 w-full sm:w-auto">Новый турнир <span aria-hidden="true">+</span></Link>
            </div>
        </div>

        <div class="dashboard-stats">
            <div class="dashboard-stat">
                <span class="eyebrow">В игре</span>
                <strong>{{ grouped.active.length }}</strong>
            </div>
            <div class="dashboard-stat">
                <span class="eyebrow">Завершено</span>
                <strong>{{ grouped.finished.length }}</strong>
            </div>
            <div class="dashboard-stat">
                <span class="eyebrow">Участий в турнирах</span>
                <strong>{{ totalParticipants }}</strong>
            </div>
        </div>

        <section v-if="!tournaments.length" class="empty-state">
            <span class="empty-state-mark" aria-hidden="true">T</span>
            <span class="eyebrow">Первый шаг за тобой</span>
            <h2 class="headline mt-2 text-2xl">Твоя лига начинается с турнира</h2>
            <p class="mt-2 max-w-md text-sm leading-relaxed text-ink-2">Создай турнир, добавь участников и поделись ссылкой. Все сохранённые турниры появятся здесь.</p>
            <Link href="/" class="btn btn-primary mt-5">Создать турнир <span aria-hidden="true">↗</span></Link>
        </section>

        <template v-for="(list, key) in grouped" :key="key">
            <section v-if="list.length" class="tournament-section">
                <div class="tournament-section-heading">
                    <div>
                        <span v-if="key === 'finished'" class="eyebrow">Архив</span>
                        <h2 class="headline mt-1 text-2xl">{{ key === 'active' ? 'Продолжаются' : 'История турниров' }}</h2>
                    </div>
                    <span class="landing-count">{{ pluralize(list.length, 'турнир', 'турнира', 'турниров') }}</span>
                </div>
                <ul v-if="key === 'finished'" class="overflow-hidden rounded-md border border-line bg-surface">
                    <li v-for="tournament in list" :key="tournament.id" class="flex flex-wrap items-center gap-4 border-b border-line p-5 last:border-0 sm:px-6">
                        <a :href="tournament.manageUrl" class="min-w-0 flex-1 hover:text-accent">
                            <strong class="block break-words text-base font-semibold">{{ tournament.name }}</strong>
                            <span class="mt-1 block text-xs text-ink-2">{{ formatDayShort(tournament.createdAt) }} · {{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}</span>
                        </a>
                        <StatusBadge :status="tournament.status" />
                        <a :href="tournament.publicUrl" :aria-label="`Результаты: ${tournament.name}`" class="p-2 text-ink-2 hover:text-accent">↗</a>
                    </li>
                </ul>
                <ul v-else class="owned-tournament-grid">
                    <li v-for="tournament in list" :key="tournament.id">
                        <article class="owned-tournament" :class="key === 'finished' ? 'owned-tournament-finished' : ''">
                            <div class="owned-tournament-top">
                                <span class="eyebrow">{{ tournament.sportLabel }}</span>
                                <StatusBadge :status="tournament.status" />
                            </div>
                            <a :href="tournament.manageUrl" class="owned-tournament-title">{{ tournament.name }}<span aria-hidden="true">↗</span></a>
                            <p class="owned-tournament-meta">
                                {{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}
                                <template v-if="tournament.matchesTotal"> · {{ tournament.matchesPlayed }} / {{ tournament.matchesTotal }} матчей</template>
                                · {{ formatDayShort(tournament.createdAt) }}
                            </p>
                            <progress v-if="tournament.matchesTotal" class="tournament-progress" :max="tournament.matchesTotal" :value="tournament.matchesPlayed" :aria-label="`Сыграно ${tournament.matchesPlayed} из ${tournament.matchesTotal} матчей`" />
                            <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-5">
                                <a :href="tournament.manageUrl" class="btn btn-secondary min-h-11">{{ tournament.status === 'draft' ? 'Продолжить настройку' : 'Открыть турнир' }} →</a>
                                <a :href="tournament.publicUrl" target="_blank" rel="noopener noreferrer" class="text-xs text-ink-2 hover:text-accent">Для зрителей ↗</a>
                            </div>
                        </article>
                    </li>
                </ul>
            </section>
        </template>
    </AppLayout>
</template>
