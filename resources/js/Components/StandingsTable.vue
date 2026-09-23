<script setup>
import { computed } from 'vue';

const props = defineProps({
    standings: { type: Array, required: true },
    names: { type: Object, required: true },
    /** Бухгольц показываем только в швейцарской системе, где он решает при равенстве очков. */
    showBuchholz: Boolean,
});

const hasDraws = computed(() => props.standings.some((r) => r.draws > 0));
const hasSets = computed(() => props.standings.some((r) => r.setsWon + r.setsLost > 0));

/** Первые три строки — условная зона выхода дальше, её помечает грань слева. */
const inZone = (row) => row.rank <= 3 && row.played > 0;
</script>

<template>
    <p v-if="!standings.length" class="rounded-sm border border-dashed border-line px-4 py-8 text-center text-sm text-ink-2">Результатов пока нет.</p>

    <ol v-if="standings.length" class="space-y-2 sm:hidden" aria-label="Турнирная таблица">
        <li
            v-for="row in standings" :key="row.participantId"
            class="rounded-sm border border-line border-l-[3px] bg-surface p-3"
            :class="inZone(row) ? 'border-l-win' : 'border-l-line'"
        >
            <div class="flex items-start gap-3">
                <span class="w-6 shrink-0 pt-0.5 text-right font-mono text-sm" :class="inZone(row) ? 'text-win' : 'text-ink-3'">{{ row.rank }}</span>
                <span class="min-w-0 flex-1 break-words text-sm font-medium">{{ names[row.participantId] ?? '?' }}</span>
                <span class="shrink-0 text-right">
                    <span class="block font-mono text-xl font-semibold leading-none">{{ row.score }}</span>
                    <span class="label mt-1 block">очки</span>
                </span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-x-3 gap-y-2 border-t border-line pt-2 text-xs">
                <div><dt class="text-ink-3">Игры</dt><dd class="font-mono text-sm">{{ row.played }}</dd></div>
                <div><dt class="text-ink-3">Победы</dt><dd class="font-mono text-sm">{{ row.wins }}</dd></div>
                <div><dt class="text-ink-3">Поражения</dt><dd class="font-mono text-sm">{{ row.losses }}</dd></div>
                <div v-if="hasDraws"><dt class="text-ink-3">Ничьи</dt><dd class="font-mono text-sm">{{ row.draws }}</dd></div>
                <div v-if="hasSets"><dt class="text-ink-3">Партии</dt><dd class="font-mono text-sm">{{ row.setsWon }}:{{ row.setsLost }}</dd></div>
                <div v-if="hasSets"><dt class="text-ink-3">Мячи</dt><dd class="font-mono text-sm">{{ row.pointsWon }}:{{ row.pointsLost }}</dd></div>
                <div v-if="showBuchholz"><dt class="text-ink-3">Бухгольц</dt><dd class="font-mono text-sm">{{ row.buchholz }}</dd></div>
            </dl>
        </li>
    </ol>

    <div v-if="standings.length" class="hidden overflow-x-auto sm:block">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b-2 border-ink">
                    <th class="label py-2 pr-2 pl-3 text-left">#</th>
                    <th class="label py-2 pr-3 text-left">Участник</th>
                    <th class="label px-3 py-2 text-right" title="Игры">И</th>
                    <th class="label px-3 py-2 text-right" title="Победы">В</th>
                    <th v-if="hasDraws" class="label px-3 py-2 text-right" title="Ничьи">Н</th>
                    <th class="label px-3 py-2 text-right" title="Поражения">П</th>
                    <th v-if="hasSets" class="label px-3 py-2 text-right">Партии</th>
                    <th v-if="hasSets" class="label hidden px-3 py-2 text-right sm:table-cell">Очки</th>
                    <th v-if="showBuchholz" class="label px-3 py-2 text-right" title="Сумма очков соперников">Бух</th>
                    <th class="label px-3 py-2 text-right" title="Очки">О</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in standings" :key="row.participantId" class="border-b border-line">
                    <td
                        class="border-l-[3px] py-2.5 pr-2 pl-2 text-right font-mono text-[13px]"
                        :class="inZone(row) ? 'border-l-win text-win' : 'border-l-transparent text-ink-3'"
                    >
                        {{ row.rank }}
                    </td>
                    <td class="py-2.5 pr-3" :class="inZone(row) ? 'font-semibold' : ''">{{ names[row.participantId] ?? '?' }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.played }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.wins }}</td>
                    <td v-if="hasDraws" class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.draws }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-2">{{ row.losses }}</td>
                    <td v-if="hasSets" class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-3">{{ row.setsWon }}:{{ row.setsLost }}</td>
                    <td v-if="hasSets" class="hidden px-3 py-2.5 text-right font-mono text-[13px] text-ink-3 sm:table-cell">{{ row.pointsWon }}:{{ row.pointsLost }}</td>
                    <td v-if="showBuchholz" class="px-3 py-2.5 text-right font-mono text-[13px] text-ink-3">{{ row.buchholz }}</td>
                    <td class="px-3 py-2.5 text-right font-mono text-[15px] font-semibold text-ink">{{ row.score }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
