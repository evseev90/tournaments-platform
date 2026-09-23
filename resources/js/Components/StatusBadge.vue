<script setup>
import { statusLabels } from '../lib/labels';

defineProps({ status: String });

const classes = {
    draft: 'bg-sunken text-ink-2',
    in_progress: 'bg-live-soft text-live',
    finished: 'bg-win-soft text-win',
};
</script>

<template>
    <span class="badge" :class="classes[status]">{{ statusLabels[status] ?? status }}</span>
</template>
