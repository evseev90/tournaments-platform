<script setup>
import AppLayout from './AppLayout.vue';
defineProps({ title: String, eyebrow: String, description: String });
</script>

<template>
    <AppLayout auth-page>
        <div class="grid min-h-[calc(100dvh-73px)] md:grid-cols-2">
            <section class="hidden flex-col justify-center gap-8 bg-board px-12 py-16 text-board-ink md:flex lg:px-16" aria-label="Табло">
                <span class="font-mono text-xs tracking-widest text-board-dim">ИГРА ПРОДОЛЖАЕТСЯ</span>
                <h2 class="font-display text-5xl leading-[1.13] font-semibold lg:text-[64px]">ТВОИ ТУРНИРЫ.<br />ТВОИ ПРАВИЛА.</h2>
                <p class="max-w-md text-lg leading-relaxed text-board-dim">Собирай участников, веди счёт и сохраняй историю каждой игры.</p>
                <div class="flex flex-wrap items-center gap-6 border-y border-board-line py-7" aria-hidden="true">
                    <span class="whitespace-nowrap font-mono text-5xl font-semibold lg:text-6xl">03 : 01</span>
                    <span class="font-mono text-xs leading-relaxed tracking-wider text-board-dim">ЕЩЁ ОДНА<br />КРАСИВАЯ ИГРА</span>
                </div>
                <span class="font-mono text-xs tracking-widest">ОРГАНИЗУЙ. ИГРАЙ. ПОВТОРЯЙ.</span>
            </section>
            <section class="mx-auto flex w-full max-w-xl flex-col justify-center gap-7 px-6 py-10 sm:px-12 lg:px-16 lg:py-16">
                <div>
                    <span class="eyebrow">{{ eyebrow }}</span>
                    <h1 class="mt-4 font-display text-4xl leading-tight font-semibold sm:text-[44px]">{{ title }}</h1>
                    <p class="mt-4 text-sm leading-relaxed text-ink-2">{{ description }}</p>
                </div>
                <slot />
                <div class="border-t border-line pt-6 text-sm"><slot name="switch" /></div>
                <p class="text-xs leading-relaxed text-ink-2">Публичные турниры доступны без регистрации.</p>
            </section>
        </div>
    </AppLayout>
</template>
