<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    baseUrl: { type: String, required: true },
});

const form = useForm({ advance_per_group: 2, third_place_match: true });

const smallestGroup = computed(() => Math.min(...props.groups.map((g) => g.standings?.length ?? 0)));
const options = computed(() => Array.from({ length: Math.max(1, smallestGroup.value) }, (_, i) => i + 1));
const total = computed(() => form.advance_per_group * props.groups.length);

function submit() {
    form.post(`${props.baseUrl}/playoff`);
}
</script>

<template>
    <form class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5" @submit.prevent="submit">
        <h3 class="font-semibold">Группы сыграны</h3>
        <p class="mt-1 text-sm text-slate-600">Соберите сетку плей-офф из лучших. Победители групп попадут на вторые места других групп.</p>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
                Проходят из группы:
                <select v-model.number="form.advance_per_group" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm">
                    <option v-for="n in options" :key="n" :value="n">{{ n }}</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.third_place_match" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
                Матч за третье место
            </label>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <span class="text-xs text-slate-500">В сетку выйдут {{ total }} участников</span>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40">
                Собрать плей-офф
            </button>
        </div>
    </form>
</template>
