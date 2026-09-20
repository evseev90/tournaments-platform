<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ email: '', password: '', remember: true });
</script>

<template>
    <Head title="Вход" />
    <AppLayout>
        <div class="mx-auto max-w-sm">
            <h1 class="mb-6 text-2xl font-semibold tracking-tight">Вход</h1>

            <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="form.post('/login')">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Почта</span>
                    <input v-model="form.email" type="email" required autofocus class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                    <span v-if="form.errors.email" class="mt-1 block text-sm text-rose-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Пароль</span>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                    <span v-if="form.errors.password" class="mt-1 block text-sm text-rose-600">{{ form.errors.password }}</span>
                </label>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
                    Запомнить меня
                </label>

                <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-700 disabled:opacity-40">
                    Войти
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-500">
                Нет аккаунта? <Link href="/register" class="text-emerald-700 hover:underline">Зарегистрируйтесь</Link>
            </p>
        </div>
    </AppLayout>
</template>
