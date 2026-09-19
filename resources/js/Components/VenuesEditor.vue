<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    venues: { type: Array, required: true },
    baseUrl: { type: String, required: true },
});

const form = useForm({ name: '' });

function add() {
    form.post(`${props.baseUrl}/venues`, { preserveScroll: true, onSuccess: () => form.reset() });
}

const editingId = ref(null);
const editingName = ref('');

function startEdit(venue) {
    editingId.value = venue.id;
    editingName.value = venue.name;
}

function saveEdit(venue) {
    if (editingName.value.trim() && editingName.value !== venue.name) {
        router.patch(`${props.baseUrl}/venues/${venue.id}`, { name: editingName.value }, { preserveScroll: true });
    }
    editingId.value = null;
}

function remove(venue) {
    if (confirm(`Убрать «${venue.name}»? Матчи с этой площадки выпадут из расписания.`)) {
        router.delete(`${props.baseUrl}/venues/${venue.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <div>
        <h3 class="mb-2 text-sm font-medium">Площадки</h3>

        <ul v-if="venues.length" class="mb-3 divide-y divide-slate-100 rounded-lg border border-slate-200">
            <li v-for="venue in venues" :key="venue.id" class="group flex items-center gap-2 px-3 py-2">
                <span class="w-5 shrink-0 text-right text-xs tabular-nums text-slate-400">{{ venue.order }}</span>
                <input
                    v-if="editingId === venue.id" v-model="editingName" autofocus
                    class="min-w-0 flex-1 rounded-md border border-emerald-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-100"
                    @keydown.enter.prevent="saveEdit(venue)" @keydown.esc="editingId = null" @blur="saveEdit(venue)"
                />
                <button v-else type="button" class="min-w-0 flex-1 truncate text-left text-sm hover:text-emerald-700" @click="startEdit(venue)">
                    {{ venue.name }}
                </button>
                <button type="button" class="rounded p-1 text-slate-400 opacity-60 hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100" @click="remove(venue)">✕</button>
            </li>
        </ul>
        <p v-else class="mb-3 text-sm text-slate-400">Столы, корты или поля, на которых идут матчи.</p>

        <form class="flex gap-2" @submit.prevent="add">
            <input
                v-model="form.name" type="text" maxlength="60" placeholder="Стол 1"
                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
            />
            <button type="submit" :disabled="!form.name.trim() || form.processing" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                Добавить
            </button>
        </form>
    </div>
</template>
