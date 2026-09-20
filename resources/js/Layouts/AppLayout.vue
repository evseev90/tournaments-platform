<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const success = computed(() => page.props.flash?.success);
const domainError = computed(() => page.props.errors?.domain);

const visible = ref(false);
watch([success, domainError], ([s, e]) => {
    visible.value = Boolean(s || e);
    if (s) setTimeout(() => (visible.value = false), 4000);
}, { immediate: true });
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <Link href="/" class="flex items-center gap-2 font-semibold tracking-tight">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-600 text-sm text-white">T</span>
                    Tournaments
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <slot name="header-actions" />
                    <template v-if="user">
                        <Link href="/my" class="text-slate-500 hover:text-slate-900">Мои турниры</Link>
                        <button type="button" class="text-slate-400 hover:text-slate-900" @click="router.post('/logout')">Выйти</button>
                    </template>
                    <template v-else>
                        <Link href="/login" class="text-slate-500 hover:text-slate-900">Войти</Link>
                    </template>
                </div>
            </div>
        </header>

        <Transition
            enter-active-class="transition duration-200" enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-200" leave-to-class="opacity-0"
        >
            <div v-if="visible" class="mx-auto mt-4 max-w-6xl px-4">
                <div
                    class="flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm"
                    :class="domainError ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                >
                    <span>{{ domainError || success }}</span>
                    <button type="button" class="text-xs opacity-70 hover:opacity-100" @click="visible = false">закрыть</button>
                </div>
            </div>
        </Transition>

        <main class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
            <slot />
        </main>

        <footer class="mx-auto max-w-6xl px-4 py-8 text-xs text-slate-400">
            Быстрые турниры без регистрации.
        </footer>
    </div>
</template>
