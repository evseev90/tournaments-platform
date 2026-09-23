<script setup>
const model = defineModel({ type: Object, required: true });

const fields = [
    ['sets_to_win', 'Партий до победы', 1, 5],
    ['points_per_set', 'Очков в партии', 1, 99],
    ['points_in_deciding_set', 'Очков в решающей', 1, 99],
    ['min_margin', 'Мин. разница', 1, 5],
];

function update(key, value) {
    model.value = { ...model.value, [key]: Number(value) };
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <label v-for="[key, label, min, max] in fields" :key="key" class="block">
            <span class="label mb-1.5 block text-[11px]">{{ label }}</span>
            <input
                type="number" :min="min" :max="max" :value="model[key]"
                class="field field-sm font-mono"
                @input="update(key, $event.target.value)"
            />
        </label>
    </div>
</template>
