<script setup>
import { ref } from 'vue';

const props = defineProps({ label: String, value: String, hint: String, danger: Boolean });
const copied = ref(false);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        window.prompt('Скопируйте ссылку', props.value);
    }
}
</script>

<template>
    <div class="min-w-0">
        <div class="mb-2 flex min-w-0 items-baseline justify-between gap-3">
            <span class="label min-w-0 truncate">{{ label }}</span>
            <span v-if="hint" class="eyebrow shrink-0" :class="danger ? 'text-danger' : ''">{{ hint }}</span>
        </div>
        <div class="flex min-w-0 items-stretch overflow-hidden rounded-sm border border-line bg-sunken focus-within:border-accent">
            <input
                :value="value" readonly
                class="w-0 min-w-0 flex-1 bg-transparent px-3 py-2 font-mono text-[13px] text-ink-2 outline-none"
                @focus="$event.target.select()"
            />
            <button
                type="button"
                class="shrink-0 border-l border-line px-3 font-display text-[13px] font-medium uppercase tracking-[0.06em] transition hover:bg-surface"
                :class="copied ? 'text-win' : 'text-ink-2'"
                @click="copy"
            >
                {{ copied ? 'Готово' : 'Копировать' }}
            </button>
        </div>
    </div>
</template>
