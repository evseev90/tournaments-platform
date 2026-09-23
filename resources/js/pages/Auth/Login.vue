<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ email: '', password: '', remember: true });
</script>

<template>
    <Head title="Вход" />
    <AppLayout>
        <div class="mx-auto max-w-sm">
            <h1 class="headline mb-6 text-4xl">Вход</h1>

            <form class="panel space-y-4 p-5" @submit.prevent="form.post('/login')">
                <label class="block">
                    <span class="label mb-1.5 block">Почта</span>
                    <input v-model="form.email" type="email" required autofocus class="field" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'login-email-error' : undefined" />
                    <span v-if="form.errors.email" id="login-email-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="label mb-1.5 block">Пароль</span>
                    <input v-model="form.password" type="password" required class="field" :aria-invalid="Boolean(form.errors.password)" :aria-describedby="form.errors.password ? 'login-password-error' : undefined" />
                    <span v-if="form.errors.password" id="login-password-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password }}</span>
                </label>

                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input v-model="form.remember" type="checkbox" class="size-4 rounded-xs accent-[var(--c-accent)]" />
                    Запомнить меня
                </label>

                <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary w-full">{{ form.processing ? 'Входим…' : 'Войти' }}</button>
            </form>

            <p class="mt-4 text-center text-sm text-ink-2">
                Нет аккаунта? <Link href="/register" class="text-accent hover:underline">Зарегистрируйтесь</Link>
            </p>
        </div>
    </AppLayout>
</template>
