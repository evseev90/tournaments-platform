<script setup>
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useDialogFocus } from '../lib/dialog';
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
const dialogElement = ref(null);

useDialogFocus(dialogElement, () => Boolean(props.entry), () => emit('close'));

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
        <div v-if="entry" class="fixed inset-0 z-50 flex items-end justify-center bg-board/70 sm:items-center sm:p-4" @click.self="emit('close')">
            <form ref="dialogElement" role="dialog" aria-modal="true" aria-labelledby="move-match-title" tabindex="-1" class="w-full max-w-sm overflow-hidden rounded-t-md border border-line bg-surface sm:rounded-md" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-3 bg-board px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="move-match-title" class="headline text-lg text-board-ink">Перенести матч</h2>
                        <p class="mt-1 truncate font-mono text-[11px] text-board-dim">{{ entry.round }} · {{ entry.title ?? 'участники ещё не известны' }}</p>
                    </div>
                    <button type="button" aria-label="Закрыть диалог" class="shrink-0 text-board-dim transition hover:text-board-ink" @click="emit('close')">✕</button>
                </div>

                <div class="space-y-3 px-5 py-4">
                    <label class="block">
                        <span class="label mb-1.5 block">Площадка</span>
                        <select v-model="venueId" data-dialog-initial-focus class="field field-sm">
                            <option v-for="venue in venues" :key="venue.id" :value="venue.id">{{ venue.name }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="label mb-1.5 block">Начало</span>
                        <input v-model="startsAt" type="datetime-local" class="field field-sm font-mono" />
                    </label>

                    <p v-if="error" role="alert" class="rounded-sm border-l-[3px] border-danger bg-danger-soft px-3 py-2 text-sm text-danger">{{ error }}</p>

                    <button type="submit" :disabled="busy" :aria-busy="busy" class="btn btn-primary w-full">{{ busy ? 'Переносим…' : 'Перенести' }}</button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
