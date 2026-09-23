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
    <section class="panel overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h2 class="headline text-lg">
                Участники
                <span class="ml-1 font-mono text-sm font-normal text-ink-3">{{ participants.length }}</span>
            </h2>
            <button v-if="participants.length > 2" type="button" class="btn btn-ghost" @click="shuffle">Жеребьёвка</button>
        </header>

        <ol v-if="participants.length">
            <li v-for="(p, index) in participants" :key="p.id" class="group flex items-center gap-3 border-b border-line px-5 py-2">
                <span class="w-6 shrink-0 text-right font-mono text-[13px] text-ink-3">{{ p.seed }}</span>

                <input
                    v-if="editingId === p.id" v-model="editingName" autofocus
                    class="field field-sm min-w-0 flex-1"
                    @keydown.enter.prevent="saveEdit(p)" @keydown.esc="editingId = null" @blur="saveEdit(p)"
                />
                <button v-else type="button" class="min-w-0 flex-1 truncate py-1 text-left text-sm transition hover:text-accent" title="Переименовать" @click="startEdit(p)">
                    {{ p.name }}
                </button>

                <div class="flex shrink-0 items-center gap-1 text-ink-3 opacity-60 transition group-hover:opacity-100">
                    <button type="button" class="rounded-xs px-1.5 py-1 transition hover:bg-sunken hover:text-ink disabled:opacity-30" :disabled="index === 0" title="Выше" @click="move(index, -1)">↑</button>
                    <button type="button" class="rounded-xs px-1.5 py-1 transition hover:bg-sunken hover:text-ink disabled:opacity-30" :disabled="index === participants.length - 1" title="Ниже" @click="move(index, 1)">↓</button>
                    <button type="button" class="rounded-xs px-1.5 py-1 transition hover:bg-danger-soft hover:text-danger" title="Удалить" @click="remove(p)">✕</button>
                </div>
            </li>
        </ol>
        <p v-else class="px-5 py-6 text-sm text-ink-3">Пока никого. Вставьте список ниже, по одному в строке.</p>

        <form class="space-y-3 px-5 py-4" @submit.prevent="add">
            <div class="flex flex-wrap items-center gap-3">
                <span class="label">Кто участвует</span>
                <select v-model="addForm.type" class="field field-sm w-auto">
                    <option v-for="(label, value) in participantTypeLabels" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <textarea v-model="addForm.text" rows="4" placeholder="Иванов&#10;Петров&#10;Сидоров" class="field text-sm" />
            <div class="flex items-center justify-between gap-3">
                <span class="font-mono text-[11px] text-ink-3">{{ lines.length ? pluralize(lines.length, 'строка', 'строки', 'строк') : 'порядок списка — это посев' }}</span>
                <button type="submit" :disabled="!lines.length || addForm.processing" class="btn btn-primary btn-sm">Добавить</button>
            </div>
            <p v-if="addForm.errors['participants.0.name'] || addForm.errors.participants" class="text-sm text-danger">
                Проверьте список: пустые или слишком длинные строки.
            </p>
        </form>
    </section>
</template>
