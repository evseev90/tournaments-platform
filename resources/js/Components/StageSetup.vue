<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    participantsCount: { type: Number, required: true },
    baseUrl: { type: String, required: true },
});

const form = useForm({ format: 'single_elimination', settings: { third_place_match: false, legs: 1 } });

const formats = [
    { value: 'single_elimination', label: 'Олимпийская', description: 'Проиграл — выбыл. Быстро, подходит для любого числа участников.', min: 2 },
    { value: 'round_robin', label: 'Круговая', description: 'Каждый с каждым, итоговая таблица. Дольше, зато честнее.', min: 3 },
];

const selected = computed(() => formats.find((f) => f.value === form.format));
const enough = computed(() => props.participantsCount >= selected.value.min);
const matches = computed(() => {
    const n = props.participantsCount;
    if (form.format === 'round_robin') return (n * (n - 1) / 2) * form.settings.legs;
    return n - 1 + (form.settings.third_place_match && n > 3 ? 1 : 0);
});

function generate() {
    form.post(`${props.baseUrl}/stage`);
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Формат</h2>
        </header>
        <form class="space-y-4 px-5 py-4" @submit.prevent="generate">
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="f in formats" :key="f.value"
                    class="cursor-pointer rounded-xl border p-4 transition"
                    :class="form.format === f.value ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300'"
                >
                    <input v-model="form.format" type="radio" :value="f.value" class="sr-only" />
                    <div class="font-medium">{{ f.label }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ f.description }}</div>
                </label>
            </div>

            <label v-if="form.format === 'single_elimination'" class="flex items-center gap-2 text-sm">
                <input v-model="form.settings.third_place_match" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
                Матч за третье место
            </label>
            <label v-else class="flex items-center gap-2 text-sm">
                Кругов:
                <select v-model.number="form.settings.legs" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                    <option :value="1">один</option>
                    <option :value="2">два, со сменой сторон</option>
                </select>
            </label>

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <span class="text-xs text-slate-400">
                    <template v-if="enough">{{ matches }} матчей</template>
                    <template v-else>Нужно минимум {{ selected.min }} участника</template>
                </span>
                <button
                    type="submit" :disabled="!enough || form.processing"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-40"
                >
                    Сгенерировать сетку
                </button>
            </div>
            <p class="text-xs text-slate-400">После генерации состав замораживается. Пока нет результатов, сетку можно отменить.</p>
        </form>
    </section>
</template>
