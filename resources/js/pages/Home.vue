<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import RulesEditor from '../Components/RulesEditor.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { pluralize } from '../lib/labels';

const props = defineProps({
    sports: { type: Array, required: true },
    running: { type: Array, default: () => [] },
});

const form = useForm({ name: '', sport: props.sports[0].value, rules: { ...props.sports[0].rules } });
const customRules = ref(false);
const participantCount = computed(() => props.running.reduce((total, tournament) => total + tournament.participants, 0));

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
        <div class="landing-home">
            <div class="landing-shell mx-auto max-w-6xl">
                <section class="landing-hero" aria-labelledby="home-title">
                    <div class="landing-intro">
                        <div class="landing-kicker">
                            <span class="landing-live-dot" aria-hidden="true" />
                            <span class="eyebrow">Место для большой игры</span>
                        </div>
                        <h1 id="home-title" class="headline landing-title">
                            Турнир<br />
                            <span>за минуту.</span>
                        </h1>
                        <p class="landing-lede">
                            От первого участника до финального свистка — всё в одном месте. Без регистрации, сложных таблиц и лишней суеты.
                        </p>

                        <ol class="landing-steps">
                            <li><span>01</span> Выбери спорт</li>
                            <li><span>02</span> Добавь участников</li>
                            <li><span>03</span> Играй и следи за счётом</li>
                        </ol>

                        <div class="landing-metrics">
                            <div>
                                <span class="eyebrow">Идут сейчас</span>
                                <div class="landing-metric-value">{{ String(running.length).padStart(2, '0') }} <small>{{ pluralize(running.length, 'турнир', 'турнира', 'турниров') }}</small></div>
                            </div>
                            <span class="landing-metric-divider" aria-hidden="true" />
                            <div>
                                <span class="eyebrow">Участников</span>
                                <div class="landing-metric-value">{{ participantCount }} <small>в игре</small></div>
                            </div>
                        </div>
                    </div>

                    <form class="landing-form panel" @submit.prevent="submit">
                        <div class="landing-form-head">
                            <div>
                                <span class="eyebrow text-board-dim">01 / Новый турнир</span>
                                <h2 class="headline mt-1 text-xl text-board-ink">Начать игру</h2>
                            </div>
                            <span class="landing-form-mark" aria-hidden="true">T</span>
                        </div>

                        <div class="landing-form-body">
                            <label class="block">
                                <span class="label mb-1.5 block">Название турнира</span>
                                <input v-model="form.name" type="text" required maxlength="100" placeholder="Например, Пятничный кубок" autofocus class="field" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'tournament-name-error' : undefined" />
                                <span v-if="form.errors.name" id="tournament-name-error" role="alert" class="mt-1.5 block text-sm text-danger">{{ form.errors.name }}</span>
                            </label>

                            <fieldset>
                                <legend class="label mb-2">Выбери дисциплину</legend>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <label
                                        v-for="sport in sports" :key="sport.value"
                                        class="landing-sport cursor-pointer rounded-sm border border-l-[3px] p-3 transition"
                                        :class="form.sport === sport.value ? 'border-line border-l-accent bg-accent-soft' : 'border-line border-l-transparent hover:border-l-line'"
                                    >
                                        <input v-model="form.sport" type="radio" :value="sport.value" class="sr-only" />
                                        <div class="font-display text-[14px] font-medium tracking-[0.04em] uppercase">{{ sport.label }}</div>
                                        <div class="mt-1 font-mono text-[9px] leading-snug text-ink-3">до {{ sport.rules.sets_to_win }} побед · партия до {{ sport.rules.points_per_set }}</div>
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

                            <button type="submit" :disabled="form.processing" :aria-busy="form.processing" class="btn btn-primary landing-submit w-full">
                                {{ form.processing ? 'Создаём турнир…' : 'Создать турнир' }}
                                <span aria-hidden="true">↗</span>
                            </button>
                            <p class="landing-form-note">Ссылка для зрителей появится сразу после создания</p>
                        </div>
                    </form>
                </section>

                <section v-if="running.length" class="landing-tournaments" aria-labelledby="running-title">
                    <div class="landing-section-heading">
                        <div>
                            <div class="landing-kicker">
                                <span class="landing-live-dot" aria-hidden="true" />
                                <span class="eyebrow">LIVE / Сейчас в игре</span>
                            </div>
                            <h2 id="running-title" class="headline mt-2 text-3xl">Турниры идут</h2>
                        </div>
                        <span class="landing-count">{{ running.length }} активных</span>
                    </div>

                    <ul class="landing-tournament-grid">
                        <li v-for="tournament in running" :key="tournament.id">
                            <a :href="tournament.publicUrl" class="landing-tournament-card group">
                                <div class="landing-tournament-top">
                                    <span class="badge bg-accent-soft text-accent">{{ tournament.sportLabel }}</span>
                                    <span v-if="tournament.matchesTotal" class="font-mono text-xs tabular-nums text-ink-2">{{ tournament.matchesPlayed }}<span class="text-ink-3"> / {{ tournament.matchesTotal }}</span></span>
                                </div>
                                <h3 class="landing-tournament-name group-hover:text-accent">{{ tournament.name }}</h3>
                                <div class="landing-tournament-foot">
                                    <span class="font-mono text-[11px] text-ink-3">{{ pluralize(tournament.participants, 'участник', 'участника', 'участников') }}</span>
                                    <span class="landing-arrow" aria-hidden="true">↗</span>
                                </div>
                                <div v-if="tournament.matchesTotal" class="landing-progress" aria-hidden="true">
                                    <span :style="{ width: `${(tournament.matchesPlayed / tournament.matchesTotal) * 100}%` }" />
                                </div>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
