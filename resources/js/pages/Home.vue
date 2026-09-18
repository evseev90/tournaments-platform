<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import RulesEditor from '../Components/RulesEditor.vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({ sports: { type: Array, required: true } });

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
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Турнир за минуту</h1>
                <p class="mt-2 text-slate-500">Без регистрации. Добавьте участников, сгенерируйте сетку, вводите счёт с телефона. Зрители смотрят по ссылке.</p>
            </div>

            <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Название</span>
                    <input
                        v-model="form.name" type="text" required maxlength="100" placeholder="Пятничный кубок" autofocus
                        class="w-full rounded-lg border border-slate-200 px-3 py-2.5 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                    />
                    <span v-if="form.errors.name" class="mt-1 block text-sm text-rose-600">{{ form.errors.name }}</span>
                </label>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Вид спорта</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label
                            v-for="sport in sports" :key="sport.value"
                            class="cursor-pointer rounded-xl border p-4 text-center transition"
                            :class="form.sport === sport.value ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-100' : 'border-slate-200 hover:border-slate-300'"
                        >
                            <input v-model="form.sport" type="radio" :value="sport.value" class="sr-only" />
                            <div class="font-medium">{{ sport.label }}</div>
                            <div class="mt-1 text-xs text-slate-500">до {{ sport.rules.sets_to_win }} побед, партия до {{ sport.rules.points_per_set }}</div>
                        </label>
                    </div>
                </fieldset>

                <div>
                    <button type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="customRules = !customRules">
                        {{ customRules ? 'Скрыть правила счёта' : 'Настроить правила счёта' }}
                    </button>
                    <div v-if="customRules" class="mt-3">
                        <RulesEditor v-model="form.rules" />
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-medium text-white hover:bg-emerald-700 disabled:opacity-40">
                    Создать турнир
                </button>
            </form>
        </div>
    </AppLayout>
</template>
