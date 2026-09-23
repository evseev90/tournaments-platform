<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Регистрация" />
    <AppLayout>
        <div class="mx-auto max-w-sm">
            <h1 class="headline mb-2 text-4xl">Регистрация</h1>
            <p class="mb-6 text-sm text-ink-2">
                Аккаунт нужен, чтобы турниры собирались в одном месте и копилась статистика. Для разового турнира он не обязателен.
            </p>

            <form class="panel space-y-4 p-5" @submit.prevent="form.post('/register')">
                <label class="block">
                    <span class="label mb-1.5 block">Имя</span>
                    <input v-model="form.name" type="text" required autofocus maxlength="100" class="field" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'register-name-error' : undefined" />
                    <span v-if="form.errors.name" id="register-name-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.name }}</span>
                </label>

                <label class="block">
                    <span class="label mb-1.5 block">Почта</span>
                    <input v-model="form.email" type="email" required class="field" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'register-email-error' : undefined" />
                    <span v-if="form.errors.email" id="register-email-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.email }}</span>
                </label>

                <label class="block">
                    <span class="label mb-1.5 block">Пароль</span>
                    <input v-model="form.password" type="password" required class="field" :aria-invalid="Boolean(form.errors.password)" :aria-describedby="form.errors.password ? 'register-password-error' : undefined" />
                    <span v-if="form.errors.password" id="register-password-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password }}</span>
                </label>

                <label class="block">
                    <span class="label mb-1.5 block">Повторите пароль</span>
                    <input v-model="form.password_confirmation" type="password" required class="field" :aria-invalid="Boolean(form.errors.password_confirmation)" :aria-describedby="form.errors.password_confirmation ? 'register-confirmation-error' : undefined" />
                    <span v-if="form.errors.password_confirmation" id="register-confirmation-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password_confirmation }}</span>
                </label>

                <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary w-full">{{ form.processing ? 'Создаём аккаунт…' : 'Создать аккаунт' }}</button>
            </form>

            <p class="mt-4 text-center text-sm text-ink-2">
                Уже есть аккаунт? <Link href="/login" class="text-accent hover:underline">Войдите</Link>
            </p>
        </div>
    </AppLayout>
</template>
