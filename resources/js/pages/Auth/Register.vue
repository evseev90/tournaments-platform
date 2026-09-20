<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Регистрация" />
    <AppLayout>
        <div class="mx-auto max-w-sm">
            <h1 class="mb-2 text-2xl font-semibold tracking-tight">Регистрация</h1>
            <p class="mb-6 text-sm text-slate-500">Аккаунт нужен, чтобы турниры собирались в одном месте и копилась статистика. Для разового турнира он не обязателен.</p>

            <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="form.post('/register')">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Имя</span>
                    <input v-model="form.name" type="text" required autofocus maxlength="100" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                    <span v-if="form.errors.name" class="mt-1 block text-sm text-rose-600">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Почта</span>
                    <input v-model="form.email" type="email" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                    <span v-if="form.errors.email" class="mt-1 block text-sm text-rose-600">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Пароль</span>
                    <input v-model="form.password" type="password" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                    <span v-if="form.errors.password" class="mt-1 block text-sm text-rose-600">{{ form.errors.password }}</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Повторите пароль</span>
                    <input v-model="form.password_confirmation" type="password" required class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" />
                </label>

                <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-700 disabled:opacity-40">
                    Создать аккаунт
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-500">
                Уже есть аккаунт? <Link href="/login" class="text-emerald-700 hover:underline">Войдите</Link>
            </p>
        </div>
    </AppLayout>
</template>
