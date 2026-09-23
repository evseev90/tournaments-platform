<script setup>
defineProps({ tabs: { type: Array, required: true } });
const model = defineModel({ type: String, required: true });
</script>

<template>
    <div class="seg" role="tablist">
        <button
            v-for="tab in tabs" :key="tab.value" type="button" role="tab"
            class="seg-btn flex-1 sm:flex-none"
            :aria-selected="model === tab.value"
            @click="model = tab.value"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
