<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({ email: '', password: '', remember: true });
</script>

<template>
    <Head title="Вход" />
    <AuthLayout title="Вход в аккаунт" eyebrow="Рады видеть снова" description="Продолжи с того места, где остановился.">
        <form class="grid gap-6" @submit.prevent="form.post('/login')">
            <label class="block">
                <span class="label mb-1.5 block">Почта</span>
                <input v-model="form.email" type="email" autocomplete="email" required autofocus class="field" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'login-email-error' : undefined" />
                <span v-if="form.errors.email" id="login-email-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.email }}</span>
            </label>

            <label class="block">
                <span class="label mb-1.5 block">Пароль</span>
                <input v-model="form.password" type="password" autocomplete="current-password" required class="field" :aria-invalid="Boolean(form.errors.password)" :aria-describedby="form.errors.password ? 'login-password-error' : undefined" />
                <span v-if="form.errors.password" id="login-password-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password }}</span>
            </label>

            <label class="auth-remember">
                <input v-model="form.remember" type="checkbox" class="size-4 rounded-xs accent-[var(--c-accent)]" />
                Запомнить меня
            </label>

            <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary min-h-12 w-full">{{ form.processing ? 'Входим…' : 'Войти →' }}</button>
        </form>
        <template #switch>
            Ещё нет аккаунта? <Link href="/register" class="font-medium text-accent transition hover:underline">Создать аккаунт →</Link>
        </template>
    </AuthLayout>
</template>
