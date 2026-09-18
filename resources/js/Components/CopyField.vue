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
    <div>
        <div class="mb-1 flex items-baseline justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</span>
            <span v-if="hint" class="text-xs" :class="danger ? 'text-rose-600' : 'text-slate-400'">{{ hint }}</span>
        </div>
        <div class="flex items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white">
            <input :value="value" readonly class="min-w-0 flex-1 bg-transparent px-3 py-2 font-mono text-sm text-slate-700 outline-none" @focus="$event.target.select()" />
            <button type="button" class="border-l border-slate-200 px-3 text-sm font-medium text-slate-600 hover:bg-slate-50" @click="copy">
                {{ copied ? 'Готово' : 'Копировать' }}
            </button>
        </div>
    </div>
</template>
