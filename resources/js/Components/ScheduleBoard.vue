<script setup>
import { computed, ref } from 'vue';
import { formatDay, formatTime, sameDay } from '../lib/time';
import { outcomeSummary } from '../lib/scoring';
import { fixtureLabel } from '../lib/labels';
import MoveMatchDialog from './MoveMatchDialog.vue';

const props = defineProps({
    tournament: { type: Object, required: true },
    schedule: { type: Object, required: true },
    editable: Boolean,
    baseUrl: { type: String, default: '' },
});

const names = computed(() => Object.fromEntries(props.tournament.participants.map((p) => [p.id, p.name])));

const fixtures = computed(() => {
    const map = new Map();
    for (const stage of props.tournament.stages) {
        for (const round of stage.rounds) {
            for (const fixture of round.fixtures) {
                map.set(fixture.id, { fixture, stage, round });
            }
        }
    }
    return map;
});

/** Дни → слоты времени → ячейки по площадкам. */
const days = computed(() => {
    const byDay = [];
    for (const assignment of props.schedule.assignments) {
        const found = fixtures.value.get(assignment.matchId);
        if (!found) continue;

        const home = names.value[found.fixture.homeId];
        const away = names.value[found.fixture.awayId];
        const entry = {
            assignment,
            fixture: found.fixture,
            // Пока оба участника не известны, имена ставить нечем: показываем, откуда они придут.
            title: home || away ? `${home ?? '?'} — ${away ?? '?'}` : null,
            round: fixtureLabel(found.stage, found.round.number, found.fixture),
        };

        let day = byDay.find((d) => sameDay(d.at, assignment.startsAt));
        if (!day) {
            day = { at: assignment.startsAt, slots: [] };
            byDay.push(day);
        }
        let slot = day.slots.find((s) => s.at === assignment.startsAt);
        if (!slot) {
            slot = { at: assignment.startsAt, byVenue: {} };
            day.slots.push(slot);
        }
        slot.byVenue[assignment.venueId] = entry;
    }
    return byDay;
});

const moving = ref(null);

function open(entry) {
    if (props.editable && entry) moving.value = entry;
}
</script>

<template>
    <div v-if="schedule.assignments.length" class="space-y-8">
        <section v-for="day in days" :key="day.at">
            <h3 class="eyebrow mb-3 border-b border-line pb-2">{{ formatDay(day.at) }}</h3>

            <div
                class="hidden gap-3 pl-16 sm:grid sm:[grid-template-columns:repeat(var(--cols),minmax(0,1fr))]"
                :style="{ '--cols': schedule.venues.length }"
            >
                <div v-for="venue in schedule.venues" :key="venue.id" class="eyebrow truncate pb-2">{{ venue.name }}</div>
            </div>

            <div class="space-y-2">
                <div v-for="slot in day.slots" :key="slot.at" class="flex gap-3">
                    <div class="w-13 shrink-0 pt-2 text-right font-mono text-[15px] font-semibold">{{ formatTime(slot.at) }}</div>
                    <div
                        class="grid flex-1 grid-cols-1 gap-3 sm:[grid-template-columns:repeat(var(--cols),minmax(0,1fr))]"
                        :style="{ '--cols': schedule.venues.length }"
                    >
                        <component
                            :is="editable && slot.byVenue[venue.id] ? 'button' : 'div'"
                            v-for="venue in schedule.venues" :key="venue.id"
                            :type="editable && slot.byVenue[venue.id] ? 'button' : undefined"
                            class="rounded-sm border px-3 py-2 text-left transition"
                            :class="slot.byVenue[venue.id]
                                ? (editable ? 'cursor-pointer border-line bg-surface hover:border-accent hover:bg-sunken focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent' : 'border-line bg-surface')
                                : 'hidden border-dashed border-line bg-sunken/50 sm:block'"
                            @click="open(slot.byVenue[venue.id])"
                        >
                            <template v-if="slot.byVenue[venue.id]">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <span class="eyebrow truncate">{{ slot.byVenue[venue.id].round }}</span>
                                    <span class="eyebrow shrink-0 sm:hidden">{{ venue.name }}</span>
                                </div>
                                <div v-if="slot.byVenue[venue.id].title" class="truncate text-sm">{{ slot.byVenue[venue.id].title }}</div>
                                <div v-else class="truncate text-sm italic text-ink-3">победители предыдущих матчей</div>
                                <div v-if="slot.byVenue[venue.id].fixture.status === 'finished'" class="mt-1 truncate font-mono text-[11px] text-win">
                                    {{ outcomeSummary(slot.byVenue[venue.id].fixture.outcome) }}
                                </div>
                            </template>
                            <span v-else class="eyebrow">свободно</span>
                        </component>
                    </div>
                </div>
            </div>
        </section>

        <MoveMatchDialog v-if="editable" :entry="moving" :venues="schedule.venues" :base-url="baseUrl" @close="moving = null" />
    </div>

    <p v-else class="py-6 text-sm text-ink-3">
        Расписание ещё не составлено.
        <template v-if="editable">Добавьте площадки и нажмите «Составить».</template>
    </p>
</template>
