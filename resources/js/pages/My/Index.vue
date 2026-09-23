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
</script>

<template>
    <Head title="Мои турниры" />
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3 border-b-2 border-ink pb-4">
            <h1 class="headline text-4xl">Мои турниры</h1>
            <div class="flex gap-2">
                <Link href="/players" class="btn btn-secondary btn-sm">Статистика</Link>
                <Link href="/" class="btn btn-primary btn-sm">Новый турнир</Link>
            </div>
        </div>

        <p v-if="!tournaments.length" class="rounded-sm border border-dashed border-line px-5 py-12 text-center text-sm text-ink-3">
            Здесь появятся турниры, созданные из этого аккаунта.
        </p>

        <template v-for="(list, key) in grouped" :key="key">
            <section v-if="list.length" class="mb-8">
                <h2 v-if="key === 'finished'" class="eyebrow mb-3">Завершённые</h2>
                <ul class="panel overflow-hidden">
                    <li v-for="(tournament, index) in list" :key="tournament.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4" :class="index ? 'border-t border-line' : ''">
                        <div class="min-w-0 flex-1">
                            <a :href="tournament.manageUrl" class="font-display text-[15px] font-medium tracking-[0.04em] uppercase transition hover:text-accent">{{ tournament.name }}</a>
                            <div class="mt-1 font-mono text-[11px] text-ink-3">
                                {{ tournament.sportLabel }} · {{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}
                                <template v-if="tournament.matchesTotal"> · {{ tournament.matchesPlayed }}/{{ tournament.matchesTotal }}</template>
                                · {{ formatDayShort(tournament.createdAt) }}
                            </div>
                        </div>
                        <StatusBadge :status="tournament.status" />
                        <a :href="tournament.publicUrl" target="_blank" class="eyebrow transition hover:text-ink">зрителям ↗</a>
                    </li>
                </ul>
            </section>
        </template>
    </AppLayout>
</template>
