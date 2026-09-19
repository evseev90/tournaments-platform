<script setup>
import { Head, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PhaseList from '../../Components/PhaseList.vue';
import ScheduleBoard from '../../Components/ScheduleBoard.vue';
import TabSwitch from '../../Components/TabSwitch.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { participantTypeLabels, sportLabels } from '../../lib/labels';

const props = defineProps({
    tournament: { type: Object, required: true },
    schedule: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const stage = computed(() => props.tournament.stages[0] ?? null);
const typeLabel = computed(() => participantTypeLabels[props.tournament.participants[0]?.type] ?? 'Участники');

const tab = ref('bracket');
const tabs = [{ value: 'bracket', label: 'Сетка' }, { value: 'schedule', label: 'Расписание' }];

usePoll(5000, { only: ['tournament', 'schedule'] });
</script>

<template>
    <Head :title="tournament.name" />
    <AppLayout>
        <div class="mb-6">
            <div class="mb-1 flex items-center gap-2 text-sm text-slate-500">
                <span>{{ sportLabels[tournament.sport] }}</span>
                <StatusBadge :status="tournament.status" />
            </div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ tournament.name }}</h1>
        </div>

        <template v-if="stage">
            <TabSwitch v-if="schedule.planned" v-model="tab" :tabs="tabs" class="mb-4 sm:w-fit" />

            <div v-show="tab === 'bracket' || !schedule.planned" class="rounded-2xl border border-slate-200 bg-white p-5">
                <PhaseList :tournament="tournament" />
            </div>

            <div v-show="tab === 'schedule' && schedule.planned" class="rounded-2xl border border-slate-200 bg-white p-5">
                <ScheduleBoard :tournament="tournament" :schedule="schedule" />
            </div>
        </template>

        <section v-else class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 font-semibold">{{ typeLabel }} <span class="text-sm font-normal text-slate-400">{{ tournament.participants.length }}</span></h2>
            <p v-if="!tournament.participants.length" class="text-sm text-slate-400">Организатор ещё собирает участников.</p>
            <ol v-else class="grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="p in tournament.participants" :key="p.id" class="flex gap-2"><span class="w-5 text-right tabular-nums text-slate-400">{{ p.seed }}</span>{{ p.name }}</li>
            </ol>
            <p class="mt-4 text-xs text-slate-400">Сетка появится здесь, когда организатор её сгенерирует. Страница обновляется сама.</p>
        </section>
    </AppLayout>
</template>
