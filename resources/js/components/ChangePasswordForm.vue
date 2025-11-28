<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex justify-between items-center border-b pb-3 mb-6">
      <h3 class="text-2xl font-bold text-gray-800">
        تغيير كلمة المرور
      </h3>
      <button 
        @click="cancelChange" 
        class="flex items-center text-gray-500 hover:text-gray-700 transition font-medium"
      >
        <Icon icon="ic:baseline-arrow-forward" class="w-5 h-5 ml-1" />
        الرجوع
      </button>
    </div>

    <form @submit.prevent="savePassword" class="space-y-5">
      
      <div>
        <label for="current-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          كلمة المرور الحالية
        </label>
        <input 
          id="current-password" 
          v-model="passwords.current"
          type="password" 
          required 
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-left ltr"
          autocomplete="current-password"
        >
      </div>

      <div>
        <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          كلمة المرور الجديدة
        </label>
        <input 
          id="new-password" 
          v-model="passwords.new"
          type="password" 
          required 
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-left ltr"
          autocomplete="new-password"
        >
        </div>

      <div>
        <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          تأكيد كلمة المرور الجديدة
        </label>
        <input 
          id="confirm-password" 
          v-model="passwords.confirm"
          type="password" 
          required 
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-left ltr"
          autocomplete="new-password"
        >
        <p v-if="passwords.new !== passwords.confirm && passwords.confirm.length > 0" class="mt-2 text-sm text-red-600 text-right">
          كلمتا المرور الجديدتان غير متطابقتين.
        </p>
      </div>

      <div class="flex justify-start gap-3 pt-4 border-t mt-6">
        <button
          type="submit"
          :disabled="passwords.new !== passwords.confirm"
          class="flex items-center justify-center px-6 py-3 bg-[#4DA1A9] text-white rounded-[30px] font-medium text-lg  hover:bg-[#5e8c90f9] transition shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Icon icon="ic:baseline-check" class="w-5 h-5 ml-2" />
          حفظ كلمة المرور
        </button>
        
        <button
          type="button"
          @click="cancelChange"
          class="flex items-center justify-center px-6 py-3 bg-gray-200 text-gray-800 rounded-[30px] font-medium text-lg  hover:border-[#a8a8a8] hover:bg-gray-300 transition shadow-md"
        >
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { defineEmits, ref } from 'vue';
import { Icon } from "@iconify/vue";

const emit = defineEmits(['passwordSaved', 'cancelChange']); 

const passwords = ref({
    current: '',
    new: '',
    confirm: ''
});

const savePassword = () => {
  // يجب إضافة منطق التحقق الإضافي هنا قبل الإرسال إلى الخادم
  if (passwords.value.new === passwords.value.confirm && passwords.value.new.length > 0) {
    // إرسال البيانات (كلمة المرور الحالية والجديدة) إلى المكون الأب
    emit('passwordSaved', { 
        currentPassword: passwords.value.current, 
        newPassword: passwords.value.new 
    });
    // إعادة تعيين الحقول بعد الحفظ (يمكن وضعها في المكون الأب بعد استدعاء الـ API)
    passwords.value.current = '';
    passwords.value.new = '';
    passwords.value.confirm = '';
  }
};

const cancelChange = () => {
    // عند الإلغاء
    emit('cancelChange');
};
</script>

<style scoped>
.ltr {
    direction: ltr;
}
</style>