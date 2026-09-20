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
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Мои турниры</h1>
            <div class="flex gap-2">
                <Link href="/players" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Статистика</Link>
                <Link href="/" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Новый турнир</Link>
            </div>
        </div>

        <p v-if="!tournaments.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-400">
            Здесь появятся турниры, созданные из этого аккаунта.
        </p>

        <template v-for="(list, key) in grouped" :key="key">
            <section v-if="list.length" class="mb-8">
                <h2 v-if="key === 'finished'" class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">Завершённые</h2>
                <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <li v-for="tournament in list" :key="tournament.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <a :href="tournament.manageUrl" class="font-medium hover:text-emerald-700">{{ tournament.name }}</a>
                            <div class="mt-0.5 text-xs text-slate-400">
                                {{ tournament.sportLabel }} · {{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}
                                <template v-if="tournament.matchesTotal"> · сыграно {{ tournament.matchesPlayed }} из {{ tournament.matchesTotal }}</template>
                                · {{ formatDayShort(tournament.createdAt) }}
                            </div>
                        </div>
                        <StatusBadge :status="tournament.status" />
                        <a :href="tournament.publicUrl" target="_blank" class="text-sm text-slate-400 hover:text-slate-700">для зрителей ↗</a>
                    </li>
                </ul>
            </section>
        </template>
    </AppLayout>
</template>
