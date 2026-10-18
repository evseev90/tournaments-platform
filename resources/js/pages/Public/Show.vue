<script setup>
import { Head, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PhaseList from '../../Components/PhaseList.vue';
import ScheduleBoard from '../../Components/ScheduleBoard.vue';
import TabSwitch from '../../Components/TabSwitch.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatLabels, participantTypeLabels, sportLabels } from '../../lib/labels';

const props = defineProps({
    tournament: { type: Object, required: true },
    schedule: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const stage = computed(() => props.tournament.stages.at(-1) ?? null);
const typeLabel = computed(() => participantTypeLabels[props.tournament.participants[0]?.type] ?? 'Участники');
const matchStats = computed(() => {
    const fixtures = props.tournament.stages.flatMap((item) => item.rounds).flatMap((round) => round.fixtures);
    const matches = fixtures.filter((fixture) => fixture.status !== 'bye');
    return { total: matches.length, played: matches.filter((match) => match.status === 'finished').length };
});

const tab = ref('bracket');
const tabs = [{ value: 'bracket', label: 'Сетка' }, { value: 'schedule', label: 'Расписание' }, { value: 'participants', label: 'Участники' }];
const refreshing = ref(false);
const refreshError = ref(false);
const updateAnnouncement = ref('');
const resultsSignature = (tournament) => JSON.stringify(tournament.stages.flatMap((item) => item.rounds).flatMap((round) => round.fixtures).map((fixture) => [fixture.id, fixture.status, fixture.outcome]));
let lastResults = resultsSignature(props.tournament);
function failedRefresh() {
    if (!refreshError.value) updateAnnouncement.value = 'Не удалось обновить счёт. Показаны последние результаты.';
    refreshError.value = true;
    return false;
}
const pollOptions = {
    only: ['tournament', 'schedule'],
    onStart: () => { refreshing.value = true; },
    onSuccess: (page) => {
        const results = resultsSignature(page.props.tournament);
        if (refreshError.value) updateAnnouncement.value = 'Связь восстановлена. Результаты актуальны.';
        else if (results !== lastResults) updateAnnouncement.value = 'Результаты обновлены.';
        lastResults = results;
        refreshError.value = false;
    },
    onFinish: () => { refreshing.value = false; },
    onError: failedRefresh,
    onNetworkError: failedRefresh,
    onHttpException: failedRefresh,
};

usePoll(5000, pollOptions);
</script>

<template>
    <Head :title="tournament.name" />
    <AppLayout public-view>
        <section class="public-hero" aria-labelledby="public-title">
            <div class="flex flex-wrap items-center gap-4">
                <StatusBadge :status="tournament.status" />
                <span class="eyebrow text-board-dim">{{ sportLabels[tournament.sport] }}</span>
            </div>
            <h1 id="public-title" class="headline public-title">{{ tournament.name }}</h1>
            <div class="public-hero-stats">
                <div><span class="eyebrow">Участников</span><strong>{{ tournament.participants.length }}</strong></div>
                <div><span class="eyebrow">Сыграно матчей</span><strong>{{ matchStats.played }} / {{ matchStats.total }}</strong></div>
                <div class="col-span-2 sm:col-span-1"><span class="eyebrow">Этап</span><strong class="public-stage-name">{{ stage ? stage.name || formatLabels[stage.format] : 'Набор участников' }}</strong></div>
            </div>
            <p class="flex flex-wrap items-center gap-2 text-xs text-board-dim">
                <span class="size-1.5 shrink-0 rounded-full" :class="refreshError ? 'bg-live' : 'bg-win'" aria-hidden="true" />
                {{ refreshError ? 'Не удалось обновить счёт. Показаны последние результаты.' : 'Результаты обновляются автоматически' }}
                <button v-if="refreshError" type="button" class="underline" :disabled="refreshing" @click="router.reload(pollOptions)">Повторить</button>
            </p>
            <span class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ updateAnnouncement }}</span>
        </section>

        <TabSwitch v-model="tab" :tabs="tabs" class="page-tabs mb-8" />
        <section v-if="tab === 'bracket'" class="min-w-0">
            <template v-if="stage">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="headline text-2xl">{{ ['single_elimination', 'double_elimination'].includes(stage.format) ? 'Путь к финалу' : 'Матчи турнира' }}</h2>
                    <span class="eyebrow">До {{ tournament.rules.sets_to_win }} побед по партиям</span>
                </div>
                <PhaseList :tournament="tournament" />
            </template>
            <div v-else class="empty-state">
                <span class="eyebrow">Подготовка турнира</span>
                <h2 class="headline mt-3 text-2xl">Сетка ещё не сформирована</h2>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-2">Организатор собирает участников. Когда сетка будет готова, она появится здесь автоматически.</p>
                <button type="button" class="btn btn-secondary mt-6 min-h-11" @click="tab = 'participants'">Посмотреть участников →</button>
            </div>
        </section>
        <section v-else-if="tab === 'schedule'" class="min-w-0">
            <h2 class="headline mb-6 text-2xl">Расписание</h2>
            <ScheduleBoard v-if="schedule.planned" :tournament="tournament" :schedule="schedule" />
            <div v-else class="empty-state">
                <h3 class="headline text-2xl">Расписание готовится</h3>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-2">Организатор ещё не назначил время и площадки. Расписание появится здесь автоматически.</p>
            </div>
        </section>
        <section v-else>
            <div class="public-board-heading"><div><span class="eyebrow">Состав участников</span><h2 class="headline mt-1 text-xl">{{ typeLabel }}</h2></div><span class="landing-count">{{ tournament.participants.length }}</span></div>
            <p v-if="!tournament.participants.length" class="text-sm text-ink-3">Организатор ещё собирает участников.</p>
            <ol v-else class="public-participant-grid">
                <li v-for="p in tournament.participants" :key="p.id" class="public-participant">
                    <span class="public-participant-seed">{{ String(p.seed).padStart(2, '0') }}</span><span class="min-w-0 flex-1 truncate">{{ p.name }}</span>
                </li>
            </ol>
            <p v-if="!stage" class="public-board-note">
                Сетка появится здесь, когда организатор её сгенерирует. Страница обновляется сама.
            </p>
        </section>
        <p class="mt-8 border-t border-line pt-6 text-xs text-ink-2">Ты смотришь публичную страницу турнира.</p>
    </AppLayout>
</template>
