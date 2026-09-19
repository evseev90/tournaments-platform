<script setup>
defineProps({ tabs: { type: Array, required: true } });
const model = defineModel({ type: String, required: true });
</script>

<template>
    <div class="flex gap-1 rounded-xl bg-slate-100 p-1">
        <button
            v-for="tab in tabs" :key="tab.value" type="button"
            class="flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition sm:flex-none"
            :class="model === tab.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
            @click="model = tab.value"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
