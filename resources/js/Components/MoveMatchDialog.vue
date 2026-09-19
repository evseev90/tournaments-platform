<script setup>
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { fromLocalInput, toLocalInput } from '../lib/time';

const props = defineProps({
    entry: { type: Object, default: null },
    venues: { type: Array, required: true },
    baseUrl: { type: String, required: true },
});
const emit = defineEmits(['close']);

const venueId = ref('');
const startsAt = ref('');
const error = ref(null);
const busy = ref(false);

watch(() => props.entry, (entry) => {
    error.value = null;
    venueId.value = entry?.assignment.venueId ?? '';
    startsAt.value = toLocalInput(entry?.assignment.startsAt);
}, { immediate: true });

function submit() {
    busy.value = true;
    router.patch(`${props.baseUrl}/schedule/matches/${props.entry.assignment.matchId}`, {
        venue_id: venueId.value,
        starts_at: fromLocalInput(startsAt.value),
    }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { error.value = errors.domain ?? Object.values(errors)[0]; },
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="entry" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" @click.self="emit('close')">
            <form class="w-full max-w-sm rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl" @submit.prevent="submit">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Перенести матч</h2>
                        <p class="text-xs text-slate-400">{{ entry.round }} · {{ entry.title ?? 'участники ещё не известны' }}</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700" @click="emit('close')">✕</button>
                </div>

                <label class="mb-3 block">
                    <span class="mb-1 block text-xs text-slate-500">Площадка</span>
                    <select v-model="venueId" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option v-for="venue in venues" :key="venue.id" :value="venue.id">{{ venue.name }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs text-slate-500">Начало</span>
                    <input v-model="startsAt" type="datetime-local" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </label>

                <p v-if="error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>

                <button type="submit" :disabled="busy" class="mt-5 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40">
                    Перенести
                </button>
            </form>
        </div>
    </Teleport>
</template>
