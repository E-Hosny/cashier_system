<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const isMobile = ref(false);
const isPwa = ref(false);
const spinning = ref(false);

function updateVisibility() {
    isMobile.value = window.matchMedia('(max-width: 767px)').matches;
    isPwa.value =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;
}

const visible = computed(() => isMobile.value && isPwa.value);

function refreshPage() {
    if (spinning.value) {
        return;
    }
    spinning.value = true;
    window.location.reload();
}

onMounted(() => {
    updateVisibility();
    window.addEventListener('resize', updateVisibility);
    window.matchMedia('(display-mode: standalone)').addEventListener?.('change', updateVisibility);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateVisibility);
});
</script>

<template>
    <button
        v-if="visible"
        type="button"
        class="fixed z-[80] flex h-11 w-11 items-center justify-center rounded-full bg-green-700 text-white shadow-lg shadow-green-900/25 ring-2 ring-white/80 transition active:scale-95"
        style="bottom: max(1rem, env(safe-area-inset-bottom, 0px)); right: max(1rem, env(safe-area-inset-right, 0px));"
        aria-label="تحديث الصفحة"
        title="تحديث"
        @click="refreshPage"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            :class="{ 'animate-spin': spinning }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.2"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"
            />
        </svg>
    </button>
</template>
