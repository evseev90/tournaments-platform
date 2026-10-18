<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CopyField from '../../Components/CopyField.vue';
import PhaseList from '../../Components/PhaseList.vue';
import PlayoffSetup from '../../Components/PlayoffSetup.vue';
import ScheduleBoard from '../../Components/ScheduleBoard.vue';
import SchedulePanel from '../../Components/SchedulePanel.vue';
import TabSwitch from '../../Components/TabSwitch.vue';
import ParticipantsEditor from '../../Components/ParticipantsEditor.vue';
import RulesEditor from '../../Components/RulesEditor.vue';
import StageSetup from '../../Components/StageSetup.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ScoreDialog from '../../Components/ScoreDialog.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { fixtureLabel, formatLabels, sportLabels } from '../../lib/labels';

const props = defineProps({
    tournament: { type: Object, required: true },
    schedule: { type: Object, required: true },
    sports: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
const canClaim = computed(() => currentUser.value !== null && props.tournament.ownerId === null);

function claim() {
    router.post(`${props.urls.manage}/claim`, {}, { preserveScroll: true });
}

const stages = computed(() => props.tournament.stages);
const stage = computed(() => stages.value[0] ?? null);
const groups = computed(() => stages.value.filter((s) => s.phase === 1));
const hasGroups = computed(() => groups.value.length > 1);
const hasPlayoff = computed(() => stages.value.some((s) => s.phase > 1));
const groupsFinished = computed(() => hasGroups.value && groups.value.every((s) => s.status === 'completed'));
const lastPhase = computed(() => Math.max(...stages.value.map((s) => s.phase), 0));
const isDraft = computed(() => props.tournament.status === 'draft');
const isRunning = computed(() => props.tournament.status === 'in_progress');
const hasResults = computed(() => stages.value
    .filter((s) => s.phase === lastPhase.value)
    .some((s) => s.rounds.some((r) => r.fixtures.some((f) => f.status === 'finished'))));
const stageCompleted = computed(() => stages.value.length > 0 && stages.value.every((s) => s.status === 'completed') && (!hasGroups.value || hasPlayoff.value));
const defaultType = computed(() => props.sports.find((s) => s.value === props.tournament.sport)?.participant_type ?? 'player');

const editingName = ref(false);
const nameForm = useForm({ name: props.tournament.name });
function saveName() {
    editingName.value = false;
    if (nameForm.name.trim() && nameForm.name !== props.tournament.name) {
        nameForm.patch(props.urls.manage, { preserveScroll: true });
    }
}

const showRules = ref(false);
const rulesForm = useForm({ rules: { ...props.tournament.rules } });
function saveRules() {
    rulesForm.patch(`${props.urls.manage}/rules`, { preserveScroll: true, onSuccess: () => (showRules.value = false) });
}

function discardStage() {
    const message = lastPhase.value > 1
        ? 'Отменить плей-офф? Групповой этап останется как есть.'
        : 'Отменить сетку? Турнир вернётся к набору участников.';
    if (confirm(message)) {
        router.delete(`${props.urls.manage}/stage`);
    }
}
function finish() {
    if (confirm('Завершить турнир? Ввод результатов закроется.')) {
        router.post(`${props.urls.manage}/finish`);
    }
}
const resultUrl = (target, fixture) => `${props.urls.manage}/stages/${target.id}/fixtures/${fixture.id}/result`;
const pairUrl = (target) => `${props.urls.manage}/stages/${target.id}/round`;

const tab = ref('bracket');
const tabs = [{ value: 'bracket', label: 'Сетка' }, { value: 'schedule', label: 'Расписание' }];
const playableMatches = computed(
    () => stages.value.flatMap((s) => s.rounds).flatMap((r) => r.fixtures).filter((f) => f.status !== 'bye').length,
);
const finishedMatches = computed(
    () => stages.value.flatMap((s) => s.rounds).flatMap((r) => r.fixtures).filter((f) => f.status === 'finished').length,
);
const names = computed(() => Object.fromEntries(props.tournament.participants.map((p) => [p.id, p.name])));
const selectedResult = ref(null);
const savingResult = ref(false);
const selectedStage = computed(() => stages.value.find((s) => s.id === selectedResult.value?.stageId));
const selectedFixture = computed(() => selectedStage.value?.rounds.flatMap((round) => round.fixtures).find((f) => f.id === selectedResult.value?.fixtureId) ?? null);
const selectedLabel = computed(() => {
    const round = selectedStage.value?.rounds.find((r) => r.fixtures.some((f) => f.id === selectedFixture.value?.id));
    return round ? `${fixtureLabel(selectedStage.value, round.number, selectedFixture.value)} · Матч ${selectedFixture.value.position + 1}` : '';
});
const nextResult = computed(() => {
    for (const s of stages.value) {
        const fixture = s.rounds.flatMap((round) => round.fixtures).find((f) => f.status === 'ready');
        if (fixture) return { stageId: s.id, fixtureId: fixture.id };
    }
    return null;
});
function openResult(selection) { if (!savingResult.value) selectedResult.value = selection; }
watch([isRunning, selectedFixture], ([running, fixture]) => {
    if (!running || (selectedResult.value && !fixture)) selectedResult.value = null;
});
</script>

<template>
    <Head :title="tournament.name" />
    <AppLayout>
        <p class="eyebrow mb-6">Управление турниром</p>
        <div class="page-heading page-heading-actions manage-page-heading">
            <div class="min-w-0">
                <div class="mb-2 flex items-center gap-3">
                    <span class="eyebrow">{{ sportLabels[tournament.sport] }}</span>
                    <StatusBadge :status="tournament.status" />
                </div>
                <form v-if="editingName" @submit.prevent="saveName">
                    <input
                        v-model="nameForm.name" autofocus maxlength="100"
                        class="field w-full max-w-lg font-display text-3xl font-semibold tracking-[0.02em] uppercase"
                        @blur="saveName" @keydown.esc="editingName = false"
                    />
                </form>
                <h1 v-else class="manage-title-heading">
                    <button type="button" class="manage-title-button" title="Переименовать турнир" @click="editingName = true">
                        <span class="headline truncate text-4xl transition hover:text-accent sm:text-5xl">{{ tournament.name }}</span>
                        <span class="manage-edit-hint">Изменить название</span>
                    </button>
                </h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="urls.public" target="_blank" rel="noopener noreferrer" class="btn btn-secondary min-h-11">Для зрителей ↗</a>
                <button v-if="isRunning && !hasResults" type="button" :disabled="savingResult" class="btn btn-secondary min-h-11" @click="discardStage">
                    {{ lastPhase > 1 ? 'Отменить плей-офф' : 'Отменить сетку' }}
                </button>
                <button v-if="isRunning && stageCompleted" type="button" :disabled="savingResult" class="btn btn-primary min-h-11" @click="finish">Завершить турнир</button>
            </div>
        </div>

        <div class="dashboard-stats manage-overview">
            <div class="dashboard-stat"><span class="eyebrow">Участники</span><strong>{{ tournament.participants.length }}</strong></div>
            <div class="dashboard-stat"><span class="eyebrow">Матчи</span><strong>{{ finishedMatches }} <small>/ {{ playableMatches }}</small></strong></div>
            <div class="dashboard-stat"><span class="eyebrow">Текущий этап</span><strong class="!font-display !text-2xl">{{ stages.at(-1)?.name || formatLabels[stages.at(-1)?.format] || 'Набор участников' }}</strong></div>
        </div>

        <div v-if="canClaim" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-sm border border-line border-l-[3px] border-l-accent bg-accent-soft px-5 py-4">
            <span class="text-sm text-ink-2">Турнир создан без аккаунта. Сохраните его себе, чтобы он попал в список и в статистику.</span>
            <button type="button" class="btn btn-primary btn-sm" @click="claim">Сохранить себе</button>
        </div>

        <div v-if="isDraft" class="grid items-start gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]">
            <ParticipantsEditor :participants="tournament.participants" :base-url="urls.manage" :default-type="defaultType" />
            <div class="space-y-6">
                <StageSetup :participants-count="tournament.participants.length" :base-url="urls.manage" />
                <section class="panel overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left" @click="showRules = !showRules">
                        <span class="headline text-lg">Правила счёта</span>
                        <span class="font-mono text-[11px] text-ink-3">
                            {{ tournament.rules.sets_to_win }} · {{ tournament.rules.points_per_set }} · {{ tournament.rules.points_in_deciding_set }}
                        </span>
                    </button>
                    <form v-if="showRules" class="space-y-3 border-t border-line px-5 py-4" @submit.prevent="saveRules">
                        <RulesEditor v-model="rulesForm.rules" />
                        <button type="submit" :disabled="rulesForm.processing" class="btn btn-primary btn-sm">Сохранить</button>
                    </form>
                </section>
            </div>
        </div>

        <template v-else-if="stage">
            <TabSwitch v-model="tab" :tabs="tabs" class="page-tabs mb-8" />

            <div v-show="tab === 'bracket'" class="space-y-6">
                <PlayoffSetup v-if="groupsFinished && !hasPlayoff && isRunning" :groups="groups" :base-url="urls.manage" />

                <div class="grid min-w-0 items-start gap-6" :class="isRunning ? 'lg:grid-cols-[minmax(0,1fr)_350px]' : ''">
                    <div class="min-w-0">
                        <PhaseList :tournament="tournament" :editable="isRunning" external-score :selected-fixture-id="selectedFixture?.id" :result-url="resultUrl" :pair-url="pairUrl" @open="openResult" />
                        <p v-if="isRunning" class="mt-6 border-t border-line pt-4 text-xs leading-relaxed text-ink-2">Нажмите на матч, чтобы ввести или исправить счёт.</p>
                    </div>
                    <aside v-if="isRunning" class="min-w-0 lg:sticky lg:top-8" aria-label="Ввод результата">
                        <ScoreDialog inline :fixture="selectedFixture" :names="names" :rules="tournament.rules" :context-label="selectedLabel" :result-url="(fixture) => resultUrl(selectedStage, fixture)" @close="selectedResult = null" @busy-change="savingResult = $event" />
                        <div v-if="!selectedFixture" class="hidden rounded-md border border-line border-t-[3px] border-t-accent bg-surface p-6 lg:block">
                            <span class="eyebrow">Результаты</span>
                            <h2 class="headline mt-3 text-2xl">{{ nextResult ? 'Следующий матч' : 'Все матчи сыграны' }}</h2>
                            <p class="mt-4 text-sm leading-relaxed text-ink-2">{{ nextResult ? 'Выберите матч в сетке или откройте следующий готовый к игре.' : 'Выберите завершённый матч, чтобы проверить или исправить его результат.' }}</p>
                            <button v-if="nextResult" type="button" class="btn btn-primary mt-6 min-h-11 w-full" :disabled="savingResult" @click="openResult(nextResult)">Внести счёт →</button>
                        </div>
                        <button v-if="!selectedFixture && nextResult" type="button" class="btn btn-primary mt-2 min-h-12 w-full lg:hidden" @click="openResult(nextResult)">Внести счёт следующего матча →</button>
                    </aside>
                </div>
            </div>

            <div v-show="tab === 'schedule'" class="grid items-start gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]">
                <div class="panel p-5">
                    <ScheduleBoard :tournament="tournament" :schedule="schedule" :editable="isRunning" :base-url="urls.manage" />
                    <p v-if="schedule.planned && isRunning" class="mt-5 border-t border-line pt-4 text-xs leading-relaxed text-ink-3">
                        Нажмите на матч, чтобы перенести его на другое время или площадку.
                    </p>
                </div>
                <SchedulePanel :schedule="schedule" :base-url="urls.manage" :matches-count="playableMatches" />
            </div>
        </template>
        <section class="mt-8 rounded-md bg-accent-soft p-6">
            <h2 class="text-base font-semibold">Один турнир — одна ссылка для зрителей</h2>
            <p class="mt-2 text-sm text-ink-2">Счёт и сетка обновляются автоматически.</p>
            <div class="mt-5"><CopyField label="Ссылка для зрителей" :value="urls.public" hint="можно делиться" /></div>
            <details class="mt-5 border-t border-line pt-4">
                <summary class="cursor-pointer text-xs text-ink-2">Доступ организатора</summary>
                <div class="mt-4"><CopyField label="Ссылка организатора" :value="urls.manage" hint="не публикуйте" danger /></div>
            </details>
        </section>
    </AppLayout>
</template>
