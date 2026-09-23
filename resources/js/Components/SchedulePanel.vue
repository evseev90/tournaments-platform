<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { fromLocalInput, toLocalInput } from '../lib/time';
import VenuesEditor from './VenuesEditor.vue';

const props = defineProps({
    schedule: { type: Object, required: true },
    baseUrl: { type: String, required: true },
    matchesCount: { type: Number, required: true },
});

const form = useForm({
    settings: {
        starts_at: toLocalInput(props.schedule.settings.starts_at),
        match_minutes: props.schedule.settings.match_minutes,
        break_minutes: props.schedule.settings.break_minutes,
        rest_minutes: props.schedule.settings.rest_minutes,
    },
});

const canPlan = computed(() => props.schedule.venues.length > 0 && props.matchesCount > 0);

const estimate = computed(() => {
    const venues = props.schedule.venues.length;
    if (!venues || !props.matchesCount) return null;
    const perVenue = Math.ceil(props.matchesCount / venues);
    const minutes = perVenue * (Number(form.settings.match_minutes) + Number(form.settings.break_minutes));
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return hours ? `${hours} ч ${rest ? `${rest} мин` : ''}`.trim() : `${rest} мин`;
});

function saveSettings() {
    form
        .transform((data) => ({ settings: { ...data.settings, starts_at: fromLocalInput(data.settings.starts_at) } }))
        .patch(`${props.baseUrl}/schedule`, { preserveScroll: true });
}

function plan() {
    router.post(`${props.baseUrl}/schedule`, {}, { preserveScroll: true });
}

function clear() {
    if (confirm('Очистить расписание? Матчи останутся, но без времени и площадок.')) {
        router.delete(`${props.baseUrl}/schedule`, { preserveScroll: true });
    }
}

const fields = [
    ['match_minutes', 'Матч, мин', 1, 600],
    ['break_minutes', 'Уборка, мин', 0, 240],
    ['rest_minutes', 'Отдых, мин', 0, 600],
];
</script>

<template>
    <section class="panel overflow-hidden">
        <header class="border-b border-line px-5 py-4">
            <h2 class="headline text-lg">Площадки и время</h2>
        </header>

        <div class="space-y-5 px-5 py-4">
            <VenuesEditor :venues="schedule.venues" :base-url="baseUrl" />

            <form class="space-y-3 border-t border-line pt-4" @submit.prevent="saveSettings">
                <label class="block">
                    <span class="label mb-1.5 block">Начало турнира</span>
                    <input v-model="form.settings.starts_at" type="datetime-local" class="field field-sm font-mono" />
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <label v-for="[key, label, min, max] in fields" :key="key" class="block">
                        <span class="label mb-1.5 block text-[11px]">{{ label }}</span>
                        <input v-model.number="form.settings[key]" type="number" :min="min" :max="max" class="field field-sm font-mono" />
                    </label>
                </div>
                <button type="submit" :disabled="form.processing" class="btn btn-ghost">Сохранить параметры</button>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                <span class="font-mono text-[11px] text-ink-3">
                    <template v-if="!schedule.venues.length">добавьте площадку</template>
                    <template v-else-if="estimate">≈ {{ estimate }} на {{ schedule.venues.length }} площадках</template>
                </span>
                <div class="flex gap-2">
                    <button v-if="schedule.planned" type="button" class="btn btn-secondary btn-sm" @click="clear">Очистить</button>
                    <button type="button" :disabled="!canPlan" class="btn btn-primary btn-sm" @click="plan">
                        {{ schedule.planned ? 'Пересоставить' : 'Составить' }}
                    </button>
                </div>
            </div>
            <p v-if="schedule.planned" class="text-xs leading-relaxed text-ink-3">Пересоставление сотрёт ручные переносы.</p>
        </div>
    </section>
</template>
