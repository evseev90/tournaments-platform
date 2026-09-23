<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import AppLayout from '../../Layouts/AppLayout.vue';
import { sportLabels } from '../../lib/labels';

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
</script>

<template>
    <Head :title="tournament.name" />
    <AppLayout>
        <template #header-actions>
            <a :href="urls.public" target="_blank" class="hidden whitespace-nowrap text-board-dim transition hover:text-board-ink sm:inline">Открыть как зритель ↗</a>
        </template>

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b-2 border-ink pb-4">
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
                <h1 v-else class="headline cursor-text truncate text-4xl transition hover:text-accent sm:text-5xl" title="Переименовать" @click="editingName = true">
                    {{ tournament.name }}
                </h1>
            </div>
            <div v-if="isRunning" class="flex gap-2">
                <button v-if="!hasResults" type="button" class="btn btn-secondary btn-sm" @click="discardStage">
                    {{ lastPhase > 1 ? 'Отменить плей-офф' : 'Отменить сетку' }}
                </button>
                <button v-if="stageCompleted" type="button" class="btn btn-primary btn-sm" @click="finish">Завершить турнир</button>
            </div>
        </div>

        <div v-if="canClaim" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-sm border border-line border-l-[3px] border-l-accent bg-accent-soft px-5 py-4">
            <span class="text-sm text-ink-2">Турнир создан без аккаунта. Сохраните его себе, чтобы он попал в список и в статистику.</span>
            <button type="button" class="btn btn-primary btn-sm" @click="claim">Сохранить себе</button>
        </div>

        <section class="panel mb-6 grid gap-5 p-5 sm:grid-cols-2">
            <CopyField label="Ссылка для зрителей" :value="urls.public" hint="можно делиться" />
            <CopyField label="Ссылка организатора" :value="urls.manage" hint="не публикуйте" danger />
        </section>

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
            <TabSwitch v-model="tab" :tabs="tabs" class="mb-5 flex sm:inline-flex" />

            <div v-show="tab === 'bracket'" class="space-y-6">
                <PlayoffSetup v-if="groupsFinished && !hasPlayoff && isRunning" :groups="groups" :base-url="urls.manage" />

                <div class="panel p-5">
                    <PhaseList :tournament="tournament" :editable="isRunning" :result-url="resultUrl" :pair-url="pairUrl" />
                    <p v-if="isRunning" class="mt-5 border-t border-line pt-4 text-xs leading-relaxed text-ink-3">
                        Нажмите на матч, чтобы ввести счёт. Уже введённый счёт можно поправить, пока не сыгран следующий матч.
                    </p>
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
    </AppLayout>
</template>
