<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import RulesEditor from '../Components/RulesEditor.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { pluralize } from '../lib/labels';

const props = defineProps({
    sports: { type: Array, required: true },
    running: { type: Array, default: () => [] },
});

const form = useForm({ name: '', sport: props.sports[0].value, rules: { ...props.sports[0].rules } });
const customRules = ref(false);

watch(() => form.sport, (sport) => {
    form.rules = { ...props.sports.find((s) => s.value === sport).rules };
});

function submit() {
    form.transform((data) => ({ ...data, rules: customRules.value ? data.rules : null })).post('/tournaments');
}
</script>

<template>
    <Head title="Новый турнир" />
    <AppLayout>
        <div class="mx-auto max-w-2xl">
            <div class="mb-8">
                <span class="eyebrow">Новый турнир</span>
                <h1 class="headline mt-2 text-5xl sm:text-6xl">Турнир за минуту</h1>
                <p class="mt-3 max-w-lg text-ink-2">
                    Без регистрации. Добавьте участников, сгенерируйте сетку, вводите счёт с телефона. Зрители смотрят по ссылке.
                </p>
            </div>

            <form class="panel space-y-6 p-5 sm:p-6" @submit.prevent="submit">
                <label class="block">
                    <span class="label mb-1.5 block">Название</span>
                    <input v-model="form.name" type="text" required maxlength="100" placeholder="Пятничный кубок" autofocus class="field" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'tournament-name-error' : undefined" />
                    <span v-if="form.errors.name" id="tournament-name-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.name }}</span>
                </label>

                <fieldset>
                    <legend class="label mb-2">Вид спорта</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="sport in sports" :key="sport.value"
                            class="cursor-pointer rounded-sm border border-l-[3px] p-4 transition"
                            :class="form.sport === sport.value ? 'border-line border-l-accent bg-accent-soft' : 'border-line border-l-transparent hover:border-l-line'"
                        >
                            <input v-model="form.sport" type="radio" :value="sport.value" class="sr-only" />
                            <div class="font-display text-[15px] font-medium tracking-[0.06em] uppercase">{{ sport.label }}</div>
                            <div class="mt-1 font-mono text-[10px] whitespace-nowrap text-ink-3">до {{ sport.rules.sets_to_win }} побед · партия до {{ sport.rules.points_per_set }}</div>
                        </label>
                    </div>
                </fieldset>

                <div>
                    <button type="button" class="btn btn-ghost" @click="customRules = !customRules">
                        {{ customRules ? 'Скрыть правила счёта' : 'Настроить правила счёта' }}
                    </button>
                    <div v-if="customRules" class="mt-3">
                        <RulesEditor v-model="form.rules" />
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary w-full">{{ form.processing ? 'Создаём турнир…' : 'Создать турнир' }}</button>
            </form>

            <section v-if="running.length" class="mt-12">
                <div class="mb-3 flex items-center gap-2">
                    <span class="size-2 rounded-full bg-live" aria-hidden="true" />
                    <h2 class="eyebrow">Идут сейчас</h2>
                </div>
                <ul class="panel overflow-hidden">
                    <li v-for="(tournament, index) in running" :key="tournament.id" :class="index ? 'border-t border-line' : ''">
                        <a :href="tournament.publicUrl" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-sunken">
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-display text-[15px] font-medium tracking-[0.04em] uppercase transition group-hover:text-accent">{{ tournament.name }}</div>
                                <div class="mt-1 font-mono text-[11px] text-ink-3">
                                    {{ tournament.sportLabel }} · {{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}
                                </div>
                            </div>
                            <div v-if="tournament.matchesTotal" class="w-24 shrink-0 text-right">
                                <div class="font-mono text-[13px] tabular-nums">{{ tournament.matchesPlayed }}/{{ tournament.matchesTotal }}</div>
                                <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-line">
                                    <div class="h-full bg-live" :style="{ width: `${(tournament.matchesPlayed / tournament.matchesTotal) * 100}%` }" />
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
