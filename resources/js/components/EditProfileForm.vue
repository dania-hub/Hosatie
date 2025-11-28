<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex justify-between items-center border-b pb-3 mb-6">
      <h3 class="text-2xl font-bold text-gray-800">
        تعديل البيانات الشخصية
      </h3>
      <button 
        @click="$emit('cancelEdit')" 
        class="flex items-center text-gray-500 hover:text-gray-700 transition font-medium"
      >
        <Icon icon="ic:baseline-arrow-forward" class="w-5 h-5 ml-1" />
        الرجوع
      </button>
    </div>

    <form @submit.prevent="saveChanges" class="space-y-5">
      
      <div>
        <label for="fullName" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          الاسم الرباعي
        </label>
        <input 
          id="fullName" 
          v-model="editedData.fullName"
          type="text" 
          required 
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-lg text-right"
        >
      </div>
      
      <div>
        <label for="jobRole" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          الدور الوظيفي
        </label>
        <input 
          id="jobRole" 
          :value="editedData.jobRole"
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
          رقم الهاتف
        </label>
        <input 
          id="phone" 
          v-model="editedData.phone"
          type="tel" 
          required 
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-lg text-right ltr"
        >
      </div>

      <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1 text-right">
          البريد الإلكتروني
        </label>
        <input 
          id="email" 
          v-model="editedData.email"
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
          class=" inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-15 w-40 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
        >
          <Icon icon="ic:baseline-check" class="w-5 h-5 ml-2" />
          حفظ التغييرات
        </button>
        
        <button
          type="button"
          @click="$emit('cancelEdit')"
          class="inline-flex items-center w-20 px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] h-15 cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
        >
          إلغاء
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, watch } from 'vue';
import { Icon } from "@iconify/vue";

const emit = defineEmits(['save', 'cancelEdit']); 

// تعريف هيكل البيانات الجديدة
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
  }
});

// تهيئة البيانات الداخلية للنموذج بالهيكل الجديد
const editedData = ref({ 
    fullName: props.initialData.fullName, 
    jobRole: props.initialData.jobRole, 
    healthCenter: props.initialData.healthCenter, 
    phone: props.initialData.phone, 
    email: props.initialData.email 
});

// لمزامنة البيانات الأولية مع النموذج كلما تم تغييرها في المكون الأب
watch(() => props.initialData, (newData) => {
    editedData.value.fullName = newData.fullName;
    editedData.value.jobRole = newData.jobRole;
    editedData.value.healthCenter = newData.healthCenter;
    editedData.value.phone = newData.phone;
    editedData.value.email = newData.email;
}, { deep: true, immediate: true });


const saveChanges = () => {
  // ملاحظة: يتم إرسال جميع البيانات بما فيها المعطلة (jobRole, healthCenter) للتأكد من اكتمال الكائن إذا لزم الأمر في الـ API، 
  // ولكن يمكن تعديلها لإرسال الحقول القابلة للتعديل فقط.
  emit('save', editedData.value);
};
</script>

<style scoped>
.ltr {
    direction: ltr;
}
</style>