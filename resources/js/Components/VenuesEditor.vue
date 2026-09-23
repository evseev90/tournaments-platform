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
        <h3 class="label mb-2">Площадки</h3>

        <ul v-if="venues.length" class="mb-3 overflow-hidden rounded-sm border border-line">
            <li v-for="(venue, index) in venues" :key="venue.id" class="group flex items-center gap-2 px-3 py-2" :class="index ? 'border-t border-line' : ''">
                <span class="w-5 shrink-0 text-right font-mono text-[11px] text-ink-3">{{ venue.order }}</span>
                <input
                    v-if="editingId === venue.id" v-model="editingName" autofocus class="field field-sm min-w-0 flex-1"
                    @keydown.enter.prevent="saveEdit(venue)" @keydown.esc="editingId = null" @blur="saveEdit(venue)"
                />
                <button v-else type="button" class="min-w-0 flex-1 truncate text-left text-sm transition hover:text-accent" @click="startEdit(venue)">
                    {{ venue.name }}
                </button>
                <button type="button" class="rounded-xs px-1.5 py-1 text-ink-3 opacity-60 transition hover:bg-danger-soft hover:text-danger group-hover:opacity-100" @click="remove(venue)">✕</button>
            </li>
        </ul>
        <p v-else class="mb-3 text-sm text-ink-3">Столы, корты или поля, на которых идут матчи.</p>

        <form class="flex gap-2" @submit.prevent="add">
            <input v-model="form.name" type="text" maxlength="60" placeholder="Стол 1" class="field field-sm min-w-0 flex-1" />
            <button type="submit" :disabled="!form.name.trim() || form.processing" class="btn btn-secondary btn-sm shrink-0">Добавить</button>
        </form>
    </div>
</template>
