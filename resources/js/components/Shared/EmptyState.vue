<template>
    <div class="flex flex-col items-center justify-center p-12 text-center bg-gray-50/50 rounded-3xl border-2 border-dashed border-gray-200 hover:border-[#4DA1A9]/30 hover:bg-gray-50 transition-all duration-300 w-full group">
        <div class="bg-white p-5 rounded-2xl shadow-sm mb-4 group-hover:scale-110 group-hover:shadow-md transition-all duration-300">
            <Icon :icon="icon" class="w-12 h-12 text-gray-300 group-hover:text-[#4DA1A9] transition-colors duration-300" />
        </div>
        
        <h3 class="text-lg font-bold text-[#2E5077] mb-2">{{ title }}</h3>
        
        <p class="text-gray-500 text-sm max-w-xs mx-auto mb-6">{{ message }}</p>
        
        <slot name="action"></slot>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
    title: {
        type: String,
        default: 'لا توجد بيانات'
    },
    message: {
        type: String,
        default: 'لا توجد بيانات متاحة للعرض حالياً.'
    },
    icon: {
        type: String,
        default: 'solar:folder-open-bold-duotone'
    }
});
</script>
