<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { isDark, toggleTheme, watchSystemTheme } from '../lib/theme';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const success = computed(() => page.props.flash?.success);
const domainError = computed(() => page.props.errors?.domain);

const visible = ref(false);
watch([success, domainError], ([s, e]) => {
    visible.value = Boolean(s || e);
    if (s) setTimeout(() => (visible.value = false), 4000);
}, { immediate: true });

onMounted(watchSystemTheme);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-bg text-ink">
        <header class="border-b border-board-line bg-board">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <Link href="/" class="flex shrink-0 items-center gap-3">
                    <span class="flex size-7 items-center justify-center rounded-xs bg-accent font-display text-base font-semibold text-accent-ink">T</span>
                    <span class="font-display text-[15px] font-medium tracking-[0.16em] text-board-ink">TOURNAMENTS</span>
                </Link>

                <div class="flex items-center gap-4 text-sm">
                    <slot name="header-actions" />
                    <template v-if="user">
                        <Link href="/my" class="text-board-dim transition hover:text-board-ink">Мои турниры</Link>
                        <button type="button" class="text-board-dim transition hover:text-board-ink" @click="router.post('/logout')">Выйти</button>
                    </template>
                    <Link v-else href="/login" class="text-board-dim transition hover:text-board-ink">Войти</Link>

                    <button
                        type="button"
                        class="rounded-xs border border-board-line px-2 py-1 font-mono text-[11px] text-board-ink transition hover:border-board-dim"
                        :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
                        @click="toggleTheme"
                    >
                        {{ isDark ? 'dark' : 'light' }}
                    </button>
                </div>
            </div>
        </header>

        <Transition
            enter-active-class="transition duration-200" enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-200" leave-to-class="opacity-0"
        >
            <div v-if="visible" class="mx-auto mt-4 w-full max-w-6xl px-4">
                <div
                    class="flex items-start justify-between gap-4 rounded-sm border-l-[3px] px-4 py-3 text-sm"
                    :class="domainError ? 'border-danger bg-danger-soft text-danger' : 'border-win bg-win-soft text-win'"
                >
                    <span>{{ domainError || success }}</span>
                    <button type="button" class="eyebrow shrink-0 pt-0.5 opacity-70 transition hover:opacity-100" @click="visible = false">закрыть</button>
                </div>
            </div>
        </Transition>

        <main class="mx-auto w-full max-w-6xl grow px-4 py-6 sm:py-8">
            <slot />
        </main>

        <footer class="mt-8 border-t border-board-line bg-board">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5">
                <span class="eyebrow text-board-dim">Быстрые турниры без регистрации</span>
                <span class="font-mono text-[11px] text-board-dim">tournaments</span>
            </div>
        </footer>
    </div>
</template>
