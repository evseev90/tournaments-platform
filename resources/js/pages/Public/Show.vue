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
        <div class="mb-6 border-b-2 border-ink pb-4">
            <div class="mb-2 flex items-center gap-3">
                <span class="eyebrow">{{ sportLabels[tournament.sport] }}</span>
                <StatusBadge :status="tournament.status" />
            </div>
            <h1 class="headline text-4xl sm:text-5xl">{{ tournament.name }}</h1>
        </div>

        <template v-if="stage">
            <TabSwitch v-if="schedule.planned" v-model="tab" :tabs="tabs" class="mb-5 flex sm:inline-flex" />

            <div v-show="tab === 'bracket' || !schedule.planned" class="panel p-5">
                <PhaseList :tournament="tournament" />
            </div>

            <div v-show="tab === 'schedule' && schedule.planned" class="panel p-5">
                <ScheduleBoard :tournament="tournament" :schedule="schedule" />
            </div>
        </template>

        <section v-else class="panel p-5">
            <h2 class="headline mb-4 border-b border-line pb-2 text-lg">
                {{ typeLabel }}
                <span class="ml-1 font-mono text-sm font-normal text-ink-3">{{ tournament.participants.length }}</span>
            </h2>
            <p v-if="!tournament.participants.length" class="text-sm text-ink-3">Организатор ещё собирает участников.</p>
            <ol v-else class="grid gap-1.5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="p in tournament.participants" :key="p.id" class="flex gap-3">
                    <span class="w-5 shrink-0 text-right font-mono text-[13px] text-ink-3">{{ p.seed }}</span>{{ p.name }}
                </li>
            </ol>
            <p class="mt-5 border-t border-line pt-4 text-xs leading-relaxed text-ink-3">
                Сетка появится здесь, когда организатор её сгенерирует. Страница обновляется сама.
            </p>
        </section>
    </AppLayout>
</template>
