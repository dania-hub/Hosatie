<template>
    <div class="flex flex-col items-center justify-center p-12 w-full animate-in fade-in duration-300">
        <div class="relative mb-4">
            <!-- Outer Ring -->
            <div class="w-16 h-16 rounded-full border-4 border-[#2E5077]/10 border-t-[#4DA1A9] animate-spin"></div>
            <!-- Inner Icon -->
            <div class="absolute inset-0 flex items-center justify-center">
                <Icon icon="solar:medical-kit-bold-duotone" class="w-6 h-6 text-[#2E5077]/40 animate-pulse" />
            </div>
        </div>
        <div class="flex flex-col items-center gap-2">
            <h3 class="text-[#2E5077] font-bold text-lg animate-pulse">{{ title }}</h3>
            <p v-if="message" class="text-gray-500 text-sm font-medium">{{ message }}</p>
        </div>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
    title: {
        type: String,
        default: 'جاري التحميل...'
    },
    message: {
        type: String,
        default: ''
    }
});
</script>
