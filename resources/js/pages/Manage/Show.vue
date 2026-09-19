<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
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
            <a :href="urls.public" target="_blank" class="text-sm text-slate-500 hover:text-slate-900">Открыть как зритель ↗</a>
        </template>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="mb-1 flex items-center gap-2 text-sm text-slate-500">
                    <span>{{ sportLabels[tournament.sport] }}</span>
                    <StatusBadge :status="tournament.status" />
                </div>
                <form v-if="editingName" @submit.prevent="saveName">
                    <input v-model="nameForm.name" autofocus maxlength="100" class="w-full max-w-lg rounded-lg border border-emerald-300 px-2 py-1 text-2xl font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-100" @blur="saveName" @keydown.esc="editingName = false" />
                </form>
                <h1 v-else class="cursor-text truncate text-2xl font-semibold tracking-tight hover:text-emerald-700 sm:text-3xl" title="Переименовать" @click="editingName = true">
                    {{ tournament.name }}
                </h1>
            </div>
            <div v-if="isRunning" class="flex gap-2">
                <button v-if="!hasResults" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50" @click="discardStage">
                    {{ lastPhase > 1 ? 'Отменить плей-офф' : 'Отменить сетку' }}
                </button>
                <button v-if="stageCompleted" type="button" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700" @click="finish">Завершить турнир</button>
            </div>
        </div>

        <section class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
            <CopyField label="Ссылка для зрителей" :value="urls.public" hint="можно делиться" />
            <CopyField label="Ссылка организатора" :value="urls.manage" hint="не публикуйте: это ключ к управлению" danger />
        </section>

        <div v-if="isDraft" class="grid gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]">
            <ParticipantsEditor :participants="tournament.participants" :base-url="urls.manage" :default-type="defaultType" />
            <div class="space-y-6">
                <StageSetup :participants-count="tournament.participants.length" :base-url="urls.manage" />
                <section class="rounded-2xl border border-slate-200 bg-white">
                    <button type="button" class="flex w-full items-center justify-between px-5 py-4 text-left" @click="showRules = !showRules">
                        <span class="font-semibold">Правила счёта</span>
                        <span class="text-xs text-slate-400">до {{ tournament.rules.sets_to_win }} побед · партия до {{ tournament.rules.points_per_set }} · решающая до {{ tournament.rules.points_in_deciding_set }}</span>
                    </button>
                    <form v-if="showRules" class="space-y-3 border-t border-slate-100 px-5 py-4" @submit.prevent="saveRules">
                        <RulesEditor v-model="rulesForm.rules" />
                        <button type="submit" :disabled="rulesForm.processing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-40">Сохранить</button>
                    </form>
                </section>
            </div>
        </div>

        <template v-else-if="stage">
            <TabSwitch v-model="tab" :tabs="tabs" class="mb-4 sm:w-fit" />

            <div v-show="tab === 'bracket'" class="space-y-6">
                <PlayoffSetup v-if="groupsFinished && !hasPlayoff && isRunning" :groups="groups" :base-url="urls.manage" />

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <PhaseList :tournament="tournament" :editable="isRunning" :result-url="resultUrl" />
                    <p v-if="isRunning" class="mt-4 text-xs text-slate-400">Нажмите на матч, чтобы ввести счёт. Уже введённый счёт можно поправить, пока не сыгран следующий матч.</p>
                </div>
            </div>

            <div v-show="tab === 'schedule'" class="grid gap-6 lg:grid-cols-[1fr_minmax(0,22rem)]">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <ScheduleBoard :tournament="tournament" :schedule="schedule" :editable="isRunning" :base-url="urls.manage" />
                    <p v-if="schedule.planned && isRunning" class="mt-4 text-xs text-slate-400">Нажмите на матч, чтобы перенести его на другое время или площадку.</p>
                </div>
                <SchedulePanel :schedule="schedule" :base-url="urls.manage" :matches-count="playableMatches" />
            </div>
        </template>
    </AppLayout>
</template>
