<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { pluralize } from '../lib/labels';

const props = defineProps({
    participantsCount: { type: Number, required: true },
    baseUrl: { type: String, required: true },
});

const form = useForm({
    format: 'single_elimination',
    settings: { third_place_match: false, legs: 1, rounds: 0 },
    groups: 2,
});

/** Столько туров нужно, чтобы выявился единоличный лидер. */
const suggestedRounds = computed(() => Math.max(1, Math.ceil(Math.log2(Math.max(2, props.participantsCount)))));

const formats = [
    { value: 'single_elimination', label: 'Олимпийская', description: 'Проиграл — выбыл. Быстро, подходит для любого числа участников.', min: 2 },
    { value: 'double_elimination', label: 'Двойная сетка', description: 'Вылет после второго поражения. Одна осечка не выбивает из турнира.', min: 3 },
    { value: 'round_robin', label: 'Круговая', description: 'Каждый с каждым, итоговая таблица. Дольше, зато честнее.', min: 2 },
    { value: 'swiss', label: 'Швейцарская', description: 'Несколько туров, соперники по силам. Подходит, когда участников много, а времени мало.', min: 3 },
    { value: 'groups', label: 'Группы и плей-офф', description: 'Сначала круговая в группах, потом сетка на выбывание.', min: 4 },
];

const selected = computed(() => formats.find((f) => f.value === form.format));
const isGroups = computed(() => form.format === 'groups');
const isSwiss = computed(() => form.format === 'swiss');
const swissRounds = computed(() => (form.settings.rounds > 0 ? form.settings.rounds : suggestedRounds.value));

const groupOptions = computed(() => {
    const max = Math.min(8, Math.floor(props.participantsCount / 2));
    return Array.from({ length: Math.max(0, max - 1) }, (_, i) => i + 2);
});

const groupSizes = computed(() => {
    const base = Math.floor(props.participantsCount / form.groups);
    const bigger = props.participantsCount % form.groups;
    return bigger ? `по ${base}, в ${bigger} из них на одного больше` : `по ${base}`;
});

const enough = computed(() => props.participantsCount >= selected.value.min && (!isGroups.value || groupOptions.value.length > 0));

const matches = computed(() => {
    const n = props.participantsCount;
    if (form.format === 'double_elimination') return 2 * n - 2;
    if (isSwiss.value) return Math.ceil(n / 2) * swissRounds.value;
    if (form.format === 'round_robin') return (n * (n - 1) / 2) * form.settings.legs;
    if (isGroups.value) {
        const base = Math.floor(n / form.groups);
        const bigger = n % form.groups;
        const small = base * (base - 1) / 2;
        const large = (base + 1) * base / 2;
        return (small * (form.groups - bigger) + large * bigger) * form.settings.legs;
    }
    return n - 1 + (form.settings.third_place_match && n > 3 ? 1 : 0);
});

function generate() {
    if (isGroups.value) {
        form.transform((data) => ({ groups: data.groups, legs: data.settings.legs })).post(`${props.baseUrl}/groups`);
        return;
    }
    form.transform((data) => ({ format: data.format, settings: data.settings })).post(`${props.baseUrl}/stage`);
}
</script>

<template>
    <section class="panel overflow-hidden">
        <header class="border-b border-line px-5 py-4">
            <h2 class="headline text-lg">Формат</h2>
        </header>
        <form class="space-y-4 px-5 py-4" @submit.prevent="generate">
            <div class="grid gap-2">
                <label
                    v-for="f in formats" :key="f.value"
                    class="cursor-pointer rounded-sm border border-l-[3px] p-4 transition"
                    :class="form.format === f.value ? 'border-line border-l-accent bg-accent-soft' : 'border-line border-l-transparent hover:border-l-line'"
                >
                    <input v-model="form.format" type="radio" :value="f.value" class="sr-only" />
                    <div class="font-display text-[15px] font-medium tracking-[0.06em] uppercase">{{ f.label }}</div>
                    <div class="mt-1 text-xs leading-relaxed text-ink-2">{{ f.description }}</div>
                </label>
            </div>

            <label v-if="form.format === 'single_elimination'" class="flex items-center gap-2 text-sm">
                <input v-model="form.settings.third_place_match" type="checkbox" class="size-4 rounded-xs accent-[var(--c-accent)]" />
                Матч за третье место
            </label>

            <p v-else-if="form.format === 'double_elimination'" class="text-xs leading-relaxed text-ink-3">
                Победитель нижней сетки играет один матч гранд-финала: переигровки при его победе не будет.
            </p>

            <template v-else-if="isSwiss">
                <label class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="label">Туров</span>
                    <select v-model.number="form.settings.rounds" class="field field-sm w-auto">
                        <option :value="0">по числу участников ({{ suggestedRounds }})</option>
                        <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                    </select>
                </label>
                <p class="text-xs leading-relaxed text-ink-3">Пары каждого тура составляются после предыдущего: соперники подбираются по набранным очкам.</p>
            </template>

            <template v-else>
                <label v-if="isGroups" class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="label">Групп</span>
                    <select v-model.number="form.groups" class="field field-sm w-auto">
                        <option v-for="n in groupOptions" :key="n" :value="n">{{ n }}</option>
                    </select>
                    <span v-if="enough" class="font-mono text-[11px] text-ink-3">{{ groupSizes }} в группе</span>
                </label>
                <label class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="label">Кругов</span>
                    <select v-model.number="form.settings.legs" class="field field-sm w-auto">
                        <option :value="1">один</option>
                        <option :value="2">два, со сменой сторон</option>
                    </select>
                </label>
            </template>

            <div class="flex items-center justify-between gap-3 border-t border-line pt-4">
                <span class="font-mono text-[11px] text-ink-3">
                    <template v-if="enough">{{ pluralize(matches, 'матч', 'матча', 'матчей') }}<template v-if="isGroups"> в группах</template></template>
                    <template v-else>нужно минимум {{ selected.min }} участника</template>
                </span>
                <button type="submit" :disabled="!enough || form.processing" class="btn btn-primary btn-sm">
                    {{ isGroups ? 'По группам' : isSwiss ? 'Первый тур' : 'Сгенерировать' }}
                </button>
            </div>
            <p class="text-xs leading-relaxed text-ink-3">После генерации состав замораживается. Пока нет результатов, этап можно отменить.</p>
        </form>
    </section>
</template>
