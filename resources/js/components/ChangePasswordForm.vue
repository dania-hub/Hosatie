<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex justify-between items-center border-b pb-3 mb-6">
      <h3 class="text-2xl font-bold text-gray-800">
        تغيير كلمة المرور
      </h3>
      <button 
        @click="cancelChange" 
        class="flex items-center text-gray-500 hover:text-gray-700 transition font-medium"
        :disabled="loading"
      >
        <Icon icon="ic:baseline-arrow-forward" class="w-5 h-5 ml-1" />
        الرجوع
      </button>
    </div>

    <!-- حالة التحميل -->
    <div v-if="loading" class="text-center py-10">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#4DA1A9] mx-auto"></div>
      <p class="mt-2 text-gray-600">جاري تحديث كلمة المرور...</p>
    </div>

    <form v-else @submit.prevent="savePassword" class="space-y-5">
      
      <!-- كلمة المرور الحالية -->
     <!-- كلمة المرور الحالية -->
<div>
  <label for="current-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
    كلمة المرور الحالية *
  </label>
  <div class="relative">
    <input 
      id="current-password" 
      v-model="passwords.current"
      :type="showPassword.current ? 'text' : 'password'" 
      required 
      :minlength="6"
      class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-right"
      autocomplete="current-password"
      dir="ltr"
    >
    <button
      type="button"
      @click="togglePasswordVisibility('current')"
      class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
      aria-label="إظهار/إخفاء كلمة المرور"
    >
      <Icon 
        :icon="showPassword.current ? 'mdi:eye-off' : 'mdi:eye'" 
        class="w-5 h-5" 
      />
    </button>
  </div>
</div>

<!-- كلمة المرور الجديدة -->
<div>
  <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
    كلمة المرور الجديدة *
  </label>
  <div class="relative">
    <input 
      id="new-password" 
      v-model="passwords.new"
      :type="showPassword.new ? 'text' : 'password'" 
      required 
      :minlength="8"
      pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
      title="يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل، وتشمل حرف كبير وصغير ورقم ورمز خاص"
      class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-right"
      autocomplete="new-password"
      dir="ltr"
    >
    <button
      type="button"
      @click="togglePasswordVisibility('new')"
      class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
      aria-label="إظهار/إخفاء كلمة المرور"
    >
      <Icon 
        :icon="showPassword.new ? 'mdi:eye-off' : 'mdi:eye'" 
        class="w-5 h-5" 
      />
    </button>
  </div>
  <p class="mt-1 text-xs text-gray-500 text-right">
    يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، رقم، ورمز خاص
  </p>
</div>

<!-- تأكيد كلمة المرور الجديدة -->
<div>
  <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1 text-right">
    تأكيد كلمة المرور الجديدة *
  </label>
  <div class="relative">
    <input 
      id="confirm-password" 
      v-model="passwords.confirm"
      :type="showPassword.confirm ? 'text' : 'password'" 
      required 
      :minlength="8"
      class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500 text-lg text-right"
      autocomplete="new-password"
      dir="ltr"
    >
    <button
      type="button"
      @click="togglePasswordVisibility('confirm')"
      class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
      aria-label="إظهار/إخفاء كلمة المرور"
    >
      <Icon 
        :icon="showPassword.confirm ? 'mdi:eye-off' : 'mdi:eye'" 
        class="w-5 h-5" 
      />
    </button>
  </div>
</div>

      <!-- أزرار التحكم -->
      <div class="flex justify-start gap-3 pt-4 border-t mt-6">
        <button
          type="submit"
          :disabled="!isFormValid || loading"
          class="flex items-center justify-center px-6 py-3 bg-[#4DA1A9] text-white rounded-[30px] font-medium text-lg hover:bg-[#5e8c90f9] transition shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Icon v-if="loading" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
          <Icon v-else icon="ic:baseline-check" class="w-5 h-5 ml-2" />
          {{ loading ? 'جاري الحفظ...' : 'حفظ كلمة المرور' }}
        </button>
        
        <button
          type="button"
          @click="cancelChange"
          :disabled="loading"
          class="flex items-center justify-center px-6 py-3 bg-gray-200 text-gray-800 rounded-[30px] font-medium text-lg hover:border-[#a8a8a8] hover:bg-gray-300 transition shadow-md disabled:opacity-50"
        >
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { defineEmits, defineProps, ref, computed, watch } from 'vue';
import { Icon } from "@iconify/vue";

const emit = defineEmits(['passwordSaved', 'cancelChange']);

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  }
});

// حالة كلمات المرور
const passwords = ref({
    current: '',
    new: '',
    confirm: ''
});

// حالة إظهار/إخفاء كلمات المرور
const showPassword = ref({
    current: false,
    new: false,
    confirm: false
});

// دالة تبديل رؤية كلمة المرور
const togglePasswordVisibility = (field) => {
    showPassword.value[field] = !showPassword.value[field];
};

// متابعة التغييرات لإعادة تعيين الأخطاء
watch(() => passwords.value.new, () => {
  validationErrors.value = validatePassword();
});

watch(() => passwords.value.confirm, () => {
  validationErrors.value = validatePassword();
});

// أخطاء التحقق من الصحة
const validationErrors = ref([]);

// التحقق من صحة كلمة المرور
const validatePassword = () => {
  const errors = [];
  
  if (passwords.value.new.length < 8) {
    errors.push("كلمة المرور يجب أن تكون 8 أحرف على الأقل");
  }
  
  if (!/(?=.*[a-z])/.test(passwords.value.new)) {
    errors.push("يجب أن تحتوي على حرف صغير واحد على الأقل");
  }
  
  if (!/(?=.*[A-Z])/.test(passwords.value.new)) {
    errors.push("يجب أن تحتوي على حرف كبير واحد على الأقل");
  }
  
  if (!/(?=.*\d)/.test(passwords.value.new)) {
    errors.push("يجب أن تحتوي على رقم واحد على الأقل");
  }
  
  if (!/(?=.*[@$!%*?&])/.test(passwords.value.new)) {
    errors.push("يجب أن تحتوي على رمز خاص واحد على الأقل (@$!%*?&)");
  }
  
  if (passwords.value.new !== passwords.value.confirm) {
    errors.push("كلمتا المرور الجديدتان غير متطابقتين");
  }
  
  return errors;
};

// التحقق من صلاحية النموذج
const isFormValid = computed(() => {
  return passwords.value.current.length >= 6 &&
         passwords.value.new.length >= 8 &&
         passwords.value.confirm.length >= 8 &&
         passwords.value.new === passwords.value.confirm &&
         validatePassword().length === 0;
});

// حفظ كلمة المرور
const savePassword = () => {
  const errors = validatePassword();
  
  if (errors.length > 0) {
    validationErrors.value = errors;
    return;
  }
  
  if (!passwords.value.current) {
    validationErrors.value = ["كلمة المرور الحالية مطلوبة"];
    return;
  }
  
  // إرسال البيانات إلى المكون الأب
  emit('passwordSaved', { 
      currentPassword: passwords.value.current, 
      newPassword: passwords.value.new 
  });
  
  // إعادة تعيين الحقول بعد الحفظ
  passwords.value.current = '';
  passwords.value.new = '';
  passwords.value.confirm = '';
  
  // إعادة تعيين حالات الإظهار
  showPassword.value = {
    current: false,
    new: false,
    confirm: false
  };
  
  validationErrors.value = [];
};

// إلغاء التغيير
const cancelChange = () => {
  passwords.value.current = '';
  passwords.value.new = '';
  passwords.value.confirm = '';
  
  // إعادة تعيين حالات الإظهار
  showPassword.value = {
    current: false,
    new: false,
    confirm: false
  };
  
  validationErrors.value = [];
  emit('cancelChange');
};
</script>

<style scoped>
.ltr {
    direction: ltr;
}

/* تحسين التنسيق لحقول الإدخال مع الأيقونة */
.relative {
  position: relative;
}

.relative input {
  padding-right: 2.5rem;
}

.relative button {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0.25rem;
  transition: color 0.2s ease;
}

.relative button:hover {
  color: #4DA1A9;
}

.relative button:focus {
  outline: 2px solid #4DA1A9;
  outline-offset: 2px;
  border-radius: 4px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>