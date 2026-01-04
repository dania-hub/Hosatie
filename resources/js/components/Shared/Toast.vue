<template>
  <div class="fixed top-2 inset-x-0 z-[2000] flex justify-center pointer-events-none px-4">
    <Transition
      enter-active-class="transition duration-500 ease-out transform"
      enter-from-class="-translate-y-full opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-300 ease-in transform"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="-translate-y-full opacity-0 scale-95"
    >
      <div
        v-if="show"
        class="pointer-events-auto min-w-[300px] max-w-sm overflow-hidden rounded-2xl shadow-xl shadow-[#2E5077]/10 border-2"
        :class="[
          type === 'success' ? 'bg-[#98c1c4] border-[#9dc0c3] text-white' :
          type === 'error' ? 'bg-red-500 border-red-500 text-white' :
          type === 'warning' ? 'bg-amber-500 border-amber-500 text-white' : 
          'bg-[#2E5077] border-[#2E5077] text-white'
        ]"
        dir="rtl"
      >
        <div class="p-3.5 flex items-center gap-3">
          <!-- Icon Container -->
          <div class="flex-shrink-0 w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
            <Icon :icon="getIcon" class="w-5 h-5 text-white" />
          </div>

          <!-- Content -->
          <div class="flex-1">
            <h3 class="text-sm font-bold leading-none mb-1">
              {{ getTitle }}
            </h3>
            <p class="text-[12px] opacity-90 font-medium leading-relaxed">
              {{ message }}
            </p>
          </div>

          <!-- Action Button (Optional) -->
          <button 
            v-if="actionLabel"
            @click="$emit('action')"
            class="flex-shrink-0 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-bold transition-all duration-200 backdrop-blur-sm ml-1"
          >
            {{ actionLabel }}
          </button>

          <!-- Close Button -->
          <button 
            @click="$emit('close')"
            class="flex-shrink-0 p-1.5 rounded-lg hover:bg-white/10 transition-colors duration-200"
          >
            <Icon icon="solar:close-circle-bold" class="w-5 h-5 text-white/70 hover:text-white" />
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Icon } from '@iconify/vue';

const props = defineProps({
  show: Boolean,
  message: String,
  type: {
    type: String,
    default: 'success'
  },
  actionLabel: {
    type: String,
    default: ''
  }
});

defineEmits(['close', 'action']);

const getIcon = computed(() => {
  switch (props.type) {
    case 'success': return 'solar:check-circle-bold';
    case 'error': return 'solar:danger-circle-bold';
    case 'warning': return 'solar:info-circle-bold';
    case 'info': return 'solar:chat-round-dots-bold';
    default: return 'solar:check-circle-bold';
  }
});

const getTitle = computed(() => {
  switch (props.type) {
    case 'success': return 'تمت العملية';
    case 'error': return 'خطأ!';
    case 'warning': return 'تنبيه';
    case 'info': return 'معلومة';
    default: return 'إشعار';
  }
});
</script>

<style scoped>
/* تطبيق خط النظام لإضفاء مظهر متناسق */
div {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
</style>
