<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'ابحث واختر...' },
    emptyText: { type: String, default: 'لا توجد نتائج' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const open = ref(false);
const query = ref('');

const normalizedOptions = computed(() =>
    props.options.map((o) => {
        if (typeof o === 'object' && o !== null) {
            return { value: o.value, label: String(o.label ?? o.value) };
        }

        return { value: o, label: String(o) };
    })
);

const selectedOption = computed(() =>
    normalizedOptions.value.find((o) => String(o.value) === String(props.modelValue))
);

const filteredOptions = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) {
        return normalizedOptions.value;
    }

    return normalizedOptions.value.filter((o) => o.label.toLowerCase().includes(q));
});

const displayValue = computed(() => {
    if (open.value) {
        return query.value;
    }

    return selectedOption.value?.label ?? '';
});

function onInput(e) {
    query.value = e.target.value;
    open.value = true;
}

function onFocus() {
    open.value = true;
    query.value = selectedOption.value?.label ?? '';
}

function pick(option) {
    emit('update:modelValue', option.value);
    query.value = option.label;
    open.value = false;
}

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) {
        open.value = false;
        query.value = selectedOption.value?.label ?? '';
    }
}

watch(
    () => props.modelValue,
    () => {
        if (!open.value) {
            query.value = selectedOption.value?.label ?? '';
        }
    }
);

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <input
            type="text"
            class="w-full border border-gray-300 rounded-lg p-2.5 bg-white"
            :placeholder="placeholder"
            :value="displayValue"
            :required="required && !modelValue"
            autocomplete="off"
            @input="onInput"
            @focus="onFocus"
        />
        <ul
            v-if="open"
            class="absolute z-50 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1"
        >
            <li
                v-if="!filteredOptions.length"
                class="px-3 py-2 text-sm text-gray-500 text-center"
            >
                {{ emptyText }}
            </li>
            <li
                v-for="opt in filteredOptions"
                :key="String(opt.value)"
                class="px-3 py-2 text-sm cursor-pointer hover:bg-cyan-50"
                :class="{ 'bg-cyan-100 font-semibold': String(opt.value) === String(modelValue) }"
                @mousedown.prevent="pick(opt)"
            >
                {{ opt.label }}
            </li>
        </ul>
    </div>
</template>
