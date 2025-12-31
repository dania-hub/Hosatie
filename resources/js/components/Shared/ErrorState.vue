<template>
    <div class="flex flex-col items-center justify-center p-8 text-center bg-red-50/80 backdrop-blur-sm rounded-3xl border border-red-100 max-w-lg mx-auto w-full animate-in zoom-in-95 duration-300 shadow-sm">
        <div class="bg-red-100 p-4 rounded-full mb-4 shadow-inner ring-4 ring-red-50">
            <Icon icon="solar:danger-triangle-bold-duotone" class="w-10 h-10 text-red-500" />
        </div>
        
        <h3 class="text-xl font-bold text-[#2E5077] mb-2">{{ title }}</h3>
        
        <p class="text-gray-600 mb-6 leading-relaxed max-w-md">{{ message }}</p>
        
        <button 
            v-if="retry" 
            @click="retry" 
            class="group px-6 py-2.5 bg-white text-red-500 font-bold rounded-xl shadow-md shadow-red-500/10 border-2 border-red-100 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all duration-300 flex items-center gap-2"
        >
            <Icon icon="solar:refresh-bold" class="w-5 h-5 group-hover:animate-spin" />
            {{ retryText }}
        </button>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
    title: {
        type: String,
        default: 'خطأ في الاتصال'
    },
    message: {
        type: String,
        default: 'تعذر الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت والمحاولة مرة أخرى.'
    },
    retry: {
        type: Function,
        default: null
    },
    retryText: {
        type: String,
        default: 'إعادة المحاولة'
    }
});
</script>
