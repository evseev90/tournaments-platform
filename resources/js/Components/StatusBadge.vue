<script setup>
import { statusLabels } from '../lib/labels';

defineProps({ status: String });

const classes = {
    draft: 'bg-amber-50 text-amber-700 ring-amber-200',
    in_progress: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    finished: 'bg-slate-100 text-slate-600 ring-slate-200',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1" :class="classes[status]">
        {{ statusLabels[status] ?? status }}
    </span>
</template>
