<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { isDark, toggleTheme, watchSystemTheme } from '../lib/theme';

const page = usePage();
defineProps({ authPage: Boolean, publicView: Boolean });
const menuOpen = ref(false);
const links = [{ href: '/my', label: 'Мои турниры' }, { href: '/players', label: 'Игроки' }];
const active = (href) => page.url.split('?')[0] === href || page.url.startsWith(`${href}/`);
watch(() => page.url, () => { menuOpen.value = false; });
const user = computed(() => page.props.auth?.user ?? null);
const success = computed(() => page.props.flash?.success);
const domainError = computed(() => page.props.errors?.domain);

const visible = ref(false);
let flashTimer;
watch([success, domainError], ([s, e]) => {
    clearTimeout(flashTimer);
    visible.value = Boolean(s || e);
    if (s && !e) flashTimer = setTimeout(() => (visible.value = false), 4000);
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(flashTimer));

let stopTheme;
onMounted(() => { stopTheme = watchSystemTheme(); });
onBeforeUnmount(() => stopTheme?.());
</script>

<template>
    <div class="flex min-h-screen flex-col bg-bg text-ink">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:bg-accent focus:p-3 focus:text-accent-ink">К содержимому</a>
        <header class="border-b border-board-line bg-board text-board-ink">
            <div class="mx-auto flex min-h-18 max-w-[1360px] items-center gap-4 px-6 sm:gap-8 sm:px-14">
                <Link href="/" aria-label="Табло — главная" class="shrink-0 font-display text-2xl font-semibold tracking-wider">ТАБЛО</Link>
                <nav v-if="user && !authPage && !publicView" aria-label="Основная навигация" class="ml-8 hidden items-center gap-8 lg:flex">
                    <Link v-for="link in links" :key="link.href" :href="link.href" :aria-current="active(link.href) ? 'page' : undefined" class="text-sm transition hover:text-board-ink" :class="active(link.href) ? 'font-semibold text-board-ink' : 'text-board-dim'">{{ link.label }}</Link>
                </nav>
                <div class="ml-auto flex items-center gap-2 text-sm sm:gap-4">
                    <Link v-if="authPage" href="/" class="text-board-dim hover:text-board-ink">На главную ↗</Link>
                    <template v-else>
                        <Link v-if="publicView" href="/" class="text-xs text-board-ink sm:text-sm"><span class="sm:hidden">Создать ↗</span><span class="hidden sm:inline">Создать турнир ↗</span></Link>
                        <template v-else-if="user">
                            <span class="hidden max-w-40 truncate font-mono text-xs text-board-dim lg:inline">{{ user.name }}</span>
                            <button type="button" class="hidden text-xs text-board-dim hover:text-board-ink lg:inline" @click="router.post('/logout')">Выйти</button>
                        </template>
                        <Link v-else href="/login" class="text-board-ink">Войти</Link>
                        <button type="button" class="min-h-10 rounded-sm border border-board-line px-3 text-xs hover:border-board-dim" :aria-label="isDark ? 'Включить светлую тему' : 'Включить тёмную тему'" @click="toggleTheme">{{ isDark ? 'Светлая' : 'Тёмная' }}</button>
                        <button v-if="user && !publicView" type="button" class="min-h-11 px-2 lg:hidden" aria-controls="mobile-navigation" :aria-expanded="menuOpen" :aria-label="menuOpen ? 'Закрыть меню' : 'Открыть меню'" @click="menuOpen = !menuOpen">{{ menuOpen ? '✕' : '☰' }}</button>
                    </template>
                </div>
            </div>
            <nav v-if="menuOpen && user && !authPage && !publicView" id="mobile-navigation" aria-label="Мобильная навигация" class="grid gap-1 border-t border-board-line px-6 py-3 lg:hidden" @keydown.esc="menuOpen = false">
                <Link v-for="link in links" :key="link.href" :href="link.href" class="py-3" :aria-current="active(link.href) ? 'page' : undefined">{{ link.label }}</Link>
                <button type="button" class="py-3 text-left text-board-dim" @click="router.post('/logout')">Выйти из аккаунта</button>
            </nav>
        </header>

        <Transition
            enter-active-class="transition duration-200" enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-200" leave-to-class="opacity-0"
        >
            <div v-if="visible" class="mx-auto mt-4 w-full max-w-6xl px-4">
                <div role="status" aria-live="polite"
                    class="flex items-start justify-between gap-4 rounded-sm border-l-[3px] px-4 py-3 text-sm"
                    :class="domainError ? 'border-danger bg-danger-soft text-danger' : 'border-win bg-win-soft text-win'"
                >
                    <span>{{ domainError || success }}</span>
                    <button type="button" class="eyebrow shrink-0 pt-0.5 opacity-70 transition hover:opacity-100" @click="visible = false">закрыть</button>
                </div>
            </div>
        </Transition>

        <main id="main-content" class="mx-auto w-full max-w-[1360px] min-w-0 grow" :class="authPage ? '' : 'px-6 py-8 sm:px-16 sm:py-12'">
            <slot />
        </main>

        <footer v-if="!authPage" class="mt-8 border-t border-line">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-5">
                <span class="eyebrow">Турниры, в которых важна игра</span>
                <span class="font-mono text-[11px] text-ink-2">ТАБЛО</span>
            </div>
        </footer>
    </div>
</template>
