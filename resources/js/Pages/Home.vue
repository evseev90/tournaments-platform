<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    laravel: String,
    php: String,
});
</script>

<template>
    <Head title="Главная" />
    <main class="mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center gap-4 px-4">
        <h1 class="text-4xl font-semibold tracking-tight">Tournaments</h1>
        <p class="text-gray-600">Laravel {{ laravel }} · PHP {{ php }} · Inertia + Vue готовы</p>
    </main>
</template>
