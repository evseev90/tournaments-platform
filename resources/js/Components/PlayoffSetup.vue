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
    <form class="rounded-sm border border-line border-l-[3px] border-l-accent bg-accent-soft p-5" @submit.prevent="submit">
        <h3 class="headline text-lg">Группы сыграны</h3>
        <p class="mt-1 text-sm text-ink-2">Соберите сетку плей-офф из лучших. Победители групп попадут на вторые места других групп.</p>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm">
                <span class="label">Проходят из группы</span>
                <select v-model.number="form.advance_per_group" class="field field-sm w-auto">
                    <option v-for="n in options" :key="n" :value="n">{{ n }}</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.third_place_match" type="checkbox" class="size-4 rounded-xs accent-[var(--c-accent)]" />
                Матч за третье место
            </label>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <span class="font-mono text-[11px] text-ink-3">в сетку выйдут {{ total }} участников</span>
            <button type="submit" :disabled="form.processing" class="btn btn-primary btn-sm">Собрать плей-офф</button>
        </div>
    </form>
</template>
