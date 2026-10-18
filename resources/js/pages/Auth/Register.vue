<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Регистрация" />
    <AuthLayout title="Создать аккаунт" eyebrow="Начни свою игру" description="Сохраняй турниры и собирай свою историю матчей.">
        <form class="grid gap-6" @submit.prevent="form.post('/register')">
            <label class="block">
                <span class="label mb-1.5 block">Имя</span>
                <input v-model="form.name" type="text" autocomplete="name" required autofocus maxlength="100" class="field" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'register-name-error' : undefined" />
                <span v-if="form.errors.name" id="register-name-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.name }}</span>
            </label>

            <label class="block">
                <span class="label mb-1.5 block">Почта</span>
                <input v-model="form.email" type="email" autocomplete="email" required class="field" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'register-email-error' : undefined" />
                <span v-if="form.errors.email" id="register-email-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.email }}</span>
            </label>

            <label class="block">
                <span class="label mb-1.5 block">Пароль</span>
                <input v-model="form.password" type="password" autocomplete="new-password" required class="field" :aria-invalid="Boolean(form.errors.password)" :aria-describedby="form.errors.password ? 'register-password-error' : undefined" />
                <span v-if="form.errors.password" id="register-password-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password }}</span>
            </label>

            <label class="block">
                <span class="label mb-1.5 block">Повторите пароль</span>
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="field" :aria-invalid="Boolean(form.errors.password_confirmation)" :aria-describedby="form.errors.password_confirmation ? 'register-confirmation-error' : undefined" />
                <span v-if="form.errors.password_confirmation" id="register-confirmation-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.password_confirmation }}</span>
            </label>

            <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary min-h-12 w-full">{{ form.processing ? 'Создаём аккаунт…' : 'Создать аккаунт →' }}</button>
        </form>
        <template #switch>
            Уже есть аккаунт? <Link href="/login" class="font-medium text-accent transition hover:underline">Войти →</Link>
        </template>
    </AuthLayout>
</template>
