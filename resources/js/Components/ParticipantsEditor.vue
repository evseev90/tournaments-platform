<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { participantTypeLabels, pluralize } from '../lib/labels';

const props = defineProps({
    participants: { type: Array, required: true },
    baseUrl: { type: String, required: true },
    defaultType: { type: String, default: 'player' },
});

const addForm = useForm({ type: props.defaultType, text: '' });
const lines = computed(() => addForm.text.split('\n').map((l) => l.trim()).filter(Boolean));

function add() {
    addForm
        .transform((data) => ({ type: data.type, participants: lines.value.map((name) => ({ name })) }))
        .post(`${props.baseUrl}/participants`, {
            preserveScroll: true,
            onSuccess: () => addForm.reset('text'),
        });
}

const editingId = ref(null);
const editingName = ref('');

function startEdit(p) {
    editingId.value = p.id;
    editingName.value = p.name;
}

function saveEdit(p) {
    if (editingName.value.trim() && editingName.value !== p.name) {
        router.patch(`${props.baseUrl}/participants/${p.id}`, { name: editingName.value }, { preserveScroll: true });
    }
    editingId.value = null;
}

function remove(p) {
    if (confirm(`Удалить «${p.name}»?`)) {
        router.delete(`${props.baseUrl}/participants/${p.id}`, { preserveScroll: true });
    }
}

function move(index, delta) {
    const order = props.participants.map((p) => p.id);
    const target = index + delta;
    if (target < 0 || target >= order.length) return;
    [order[index], order[target]] = [order[target], order[index]];
    router.post(`${props.baseUrl}/participants/reorder`, { order }, { preserveScroll: true });
}

function shuffle() {
    router.post(`${props.baseUrl}/participants/shuffle`, {}, { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Участники <span class="ml-1 text-sm font-normal text-slate-400">{{ participants.length }}</span></h2>
            <button v-if="participants.length > 2" type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="shuffle">
                Жеребьёвка
            </button>
        </header>

        <ol v-if="participants.length" class="divide-y divide-slate-100">
            <li v-for="(p, index) in participants" :key="p.id" class="group flex items-center gap-3 px-5 py-2.5">
                <span class="w-6 shrink-0 text-right text-sm tabular-nums text-slate-400">{{ p.seed }}</span>

                <input
                    v-if="editingId === p.id" v-model="editingName" autofocus
                    class="min-w-0 flex-1 rounded-md border border-emerald-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-100"
                    @keydown.enter.prevent="saveEdit(p)" @keydown.esc="editingId = null" @blur="saveEdit(p)"
                />
                <button v-else type="button" class="min-w-0 flex-1 truncate text-left text-sm hover:text-emerald-700" title="Переименовать" @click="startEdit(p)">
                    {{ p.name }}
                </button>

                <div class="flex shrink-0 items-center gap-1 text-slate-400 opacity-60 group-hover:opacity-100">
                    <button type="button" class="rounded p-1 hover:bg-slate-100 disabled:opacity-30" :disabled="index === 0" title="Выше" @click="move(index, -1)">↑</button>
                    <button type="button" class="rounded p-1 hover:bg-slate-100 disabled:opacity-30" :disabled="index === participants.length - 1" title="Ниже" @click="move(index, 1)">↓</button>
                    <button type="button" class="rounded p-1 hover:bg-rose-50 hover:text-rose-600" title="Удалить" @click="remove(p)">✕</button>
                </div>
            </li>
        </ol>
        <p v-else class="px-5 py-6 text-sm text-slate-400">Пока никого. Вставьте список ниже, по одному в строке.</p>

        <form class="space-y-3 border-t border-slate-100 px-5 py-4" @submit.prevent="add">
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm text-slate-500">Кто участвует:</label>
                <select v-model="addForm.type" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                    <option v-for="(label, value) in participantTypeLabels" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <textarea
                v-model="addForm.text" rows="4" placeholder="Иванов&#10;Петров&#10;Сидоров"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            />
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs text-slate-400">{{ lines.length ? pluralize(lines.length, 'строка', 'строки', 'строк') : 'Порядок списка — это посев' }}</span>
                <button
                    type="submit" :disabled="!lines.length || addForm.processing"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40"
                >
                    Добавить
                </button>
            </div>
            <p v-if="addForm.errors['participants.0.name'] || addForm.errors.participants" class="text-sm text-rose-600">Проверьте список: пустые или слишком длинные строки.</p>
        </form>
    </section>
</template>
