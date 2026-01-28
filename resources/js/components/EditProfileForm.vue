<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex justify-between items-center border-b pb-3 mb-6">
      <h3 class="text-2xl font-bold text-gray-800">
        تعديل البيانات الشخصية
      </h3>
      <button 
        @click="$emit('cancelEdit')" 
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
      <p class="mt-2 text-gray-600">جاري التحديث...</p>
    </div>

    <form v-else @submit.prevent="saveChanges" class="space-y-5">
      
      <div>
        <label for="fullName" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          الاسم الرباعي *
        </label>
        <input 
          id="fullName" 
          v-model="editedData.fullName"
          type="text" 
          required 
          :disabled="loading"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-lg text-right disabled:bg-gray-100"
        >
      </div>
      
      <div>
        <label for="jobRole" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          الدور الوظيفي
        </label>
        <input 
          id="jobRole" 
          :value="translateRole(editedData.jobRole)"
          type="text" 
          disabled
          class="w-full px-4 py-2 border border-gray-200 bg-gray-100 rounded-lg text-lg text-right cursor-not-allowed"
        >
      </div>
      
      <div>
        <label for="healthCenter" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          المركز الصحي
        </label>
        <input 
          id="healthCenter" 
          :value="editedData.healthCenter"
          type="text" 
          disabled
          class="w-full px-4 py-2 border border-gray-200 bg-gray-100 rounded-lg text-lg text-right cursor-not-allowed"
        >
      </div>

      <div>
        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          رقم الهاتف *
        </label>
        <input 
          id="phone" 
          v-model="editedData.phone"
          type="tel" 
          required 
          :disabled="loading"
          pattern="[0-9]{10}"
          title="يرجى إدخال رقم هاتف صحيح (10 أرقام)"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-lg text-right ltr disabled:bg-gray-100"
        >
      </div>

      <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          البريد الإلكتروني
        </label>
        <input 
          id="email" 
          :value="editedData.email"
          type="email" 
          disabled 
          class="w-full px-4 py-2 border border-gray-200 bg-gray-100 rounded-lg text-lg text-right cursor-not-allowed ltr"
        >
      </div>
      
      <p class="text-sm text-gray-500 text-right pt-2 border-t mt-6">
        <Icon icon="ic:baseline-info" class="w-4 h-4 inline-block ml-1 align-middle text-blue-500" />
        ملاحظة: البيانات الوظيفية لا يمكن تعديلها.
      </p>

      <div class="flex justify-start gap-3 pt-4 border-t">
        <button
          type="submit"
          :disabled="loading || !isFormChanged"
          class="inline-flex items-center justify-center px-6 py-3 border-2 border-[#ffffff8d] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Icon v-if="loading" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
          <Icon v-else icon="ic:baseline-check" class="w-5 h-5 ml-2" />
          {{ loading ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
        </button>
        
        <button
          type="button"
          @click="$emit('cancelEdit')"
          :disabled="loading"
          class="inline-flex items-center justify-center px-6 py-3 border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb] disabled:opacity-50"
        >
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, watch, computed } from 'vue';
import { Icon } from "@iconify/vue";

const emit = defineEmits(['save', 'cancelEdit']); 

const props = defineProps({
  initialData: { 
      type: Object,
      default: () => ({ 
        fullName: '', 
        jobRole: '', 
        healthCenter: '', 
        phone: '', 
        email: '' 
      }),
  },
  loading: {
    type: Boolean,
    default: false
  }
});

// تهيئة البيانات الداخلية للنموذج
const editedData = ref({ 
    fullName: props.initialData.fullName, 
    jobRole: props.initialData.jobRole, 
    healthCenter: props.initialData.healthCenter, 
    phone: props.initialData.phone, 
    email: props.initialData.email 
});

// لمزامنة البيانات الأولية مع النموذج
watch(() => props.initialData, (newData) => {
    editedData.value.fullName = newData.fullName;
    editedData.value.jobRole = newData.jobRole;
    editedData.value.healthCenter = newData.healthCenter;
    editedData.value.phone = newData.phone;
    editedData.value.email = newData.email;
}, { deep: true, immediate: true });

// التحقق إذا كان النموذج قد تغير
const isFormChanged = computed(() => {
  return editedData.value.fullName !== props.initialData.fullName ||
         editedData.value.phone !== props.initialData.phone;
});

// دالة تعريب الدور الوظيفي
const translateRole = (role) => {
    const roleTranslations = {
        'hospital_admin': 'مدير نظام المستشفى',
        'supplier_admin': ' مورد',
        'super_admin': 'المدير الأعلى',
        'warehouse_manager': 'مسؤول المخزن',
        'pharmacist': 'صيدلي',
        'doctor': 'طبيب',
        'department_head': 'مدير القسم',
        'patient': 'مريض',
        'data_entry': 'مدخل بيانات',
        'department_admin': 'مدير القسم'
    };
    
    return roleTranslations[role] || role || 'غير محدد';
};

const saveChanges = () => {
  // التحقق من صحة البيانات قبل الإرسال
  if (!editedData.value.fullName.trim()) {
    alert("الاسم الرباعي مطلوب");
    return;
  }
  
  if (!editedData.value.phone.trim()) {
    alert("رقم الهاتف مطلوب");
    return;
  }
  
  // التحقق من صحة رقم الهاتف
  const phoneRegex = /^[0-9]{10}$/;
  if (!phoneRegex.test(editedData.value.phone)) {
    alert("يرجى إدخال رقم هاتف صحيح (10 أرقام)");
    return;
  }
  
  emit('save', editedData.value);
};
</script>

<style scoped>
.ltr {
    direction: ltr;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>