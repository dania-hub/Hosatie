<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    availableSuppliers: Array,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
  name: "",
  
  address: "",
  city: "",
  region: "",
  phone: "",
  email: "",
  managerId: "",
  supplierId: "",
});

// أخطاء التحقق
const errors = ref({
  name: false,
  city: false,
  phone: false,
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// إعادة تعيين النموذج
const resetForm = () => {
    form.value = { 
        name: "",
      
        address: "",
        city: "",
        region: "",
        phone: "",
     
        managerId: "",
        supplierId: "",
    };
    errors.value = { 
        name: false, 
        city: false,
        phone: false,
    };
};

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    errors.value.name = !data.name || data.name.trim().length < 2;
    if (errors.value.name) isValid = false;

    errors.value.city = !data.city || data.city.trim().length < 2;
    if (errors.value.city) isValid = false;

    // رقم الهاتف اختياري ولكن إذا تم إدخاله يجب أن يكون صالحاً
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^[\d\s\+\-\(\)]+$/;
        errors.value.phone = !phoneRegex.test(data.phone);
        if (errors.value.phone) isValid = false;
    } else {
        errors.value.phone = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.city || data.city.trim().length < 2) return false;
    
    return true;
});

// إرسال النموذج
const submitForm = () => {
    if (validateForm()) {
        showConfirmationModal();
    }
};

// عرض نافذة التأكيد
const showConfirmationModal = () => {
    isConfirmationModalOpen.value = true;
};

// إغلاق نافذة التأكيد
const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
};

// تأكيد التسجيل
const confirmRegistration = () => {
    // البحث عن اسم المورد إذا تم اختياره
    let supplierName = "";
    if (form.value.supplierId) {
        const selectedSupplier = props.availableSuppliers.find(
            supplier => supplier.id === form.value.supplierId
        );
        supplierName = selectedSupplier ? selectedSupplier.name : "";
    }

    // البحث عن اسم المدير إذا تم اختياره
    let managerName = "";
    let managerEmail = "";
    let managerPhone = "";
    if (form.value.managerId) {
        const selectedManager = props.availableManagers.find(
            manager => manager.id === form.value.managerId
        );
        if (selectedManager) {
            managerName = selectedManager.name || "";
            managerEmail = selectedManager.email || "";
            managerPhone = selectedManager.phone || "";
        }
    }
    
    const newHospital = {
        name: form.value.name,
       
        address: form.value.address,
        city: form.value.city,
        region: form.value.region,
        phone: form.value.phone,
       
        managerId: form.value.managerId || null,
        managerName: managerName,
        managerEmail: managerEmail,
        managerPhone: managerPhone,
        supplierId: form.value.supplierId || null,
        supplierName: supplierName,
    };
    
    emit('save', newHospital);
    closeConfirmationModal();
    closeModal();
};

// إغلاق النافذة
const closeModal = () => {
    resetForm();
    emit('close');
};

// إعادة تعيين النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        resetForm();
    }
});
</script>

<template>
    <!-- Modal الرئيسي -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4" 
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

         <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:hospital-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    إضافة مستشفى جديد
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- اسم المستشفى -->
                    <div class="space-y-2">
                        <Label for="name" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المستشفى
                        </Label>
                        <div class="relative">
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="أدخل اسم المستشفى"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.name }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المستشفى (على الأقل حرفين)
                        </p>
                    </div>

                    <!-- رمز المستشفى -->
                   
                    <!-- المدينة -->
                    <div class="space-y-2">
                        <Label for="city" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المدينة
                        </Label>
                        <div class="relative">
                            <Input
                                id="city"
                                v-model="form.city"
                                placeholder="أدخل اسم المدينة"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.city, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.city }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="errors.city" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المدينة
                        </p>
                    </div>

                    <!-- المنطقة -->
                    <div class="space-y-2">
                        <Label for="region" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المنطقة (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="region"
                                v-model="form.region"
                                placeholder="أدخل اسم المنطقة"
                                type="text"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                    </div>

                    <!-- رقم الهاتف -->
                    <div class="space-y-2">
                        <Label for="phone" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="phone"
                                v-model="form.phone"
                                placeholder="مثال: 05XXXXXXXX"
                                type="tel"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.phone, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.phone }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="errors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            رقم الهاتف غير صالح
                        </p>
                    </div>

                   

                    <!-- العنوان -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="address" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            العنوان التفصيلي (اختياري)
                        </Label>
                        <div class="relative">
                            <textarea
                                id="address"
                                v-model="form.address"
                                placeholder="أدخل العنوان التفصيلي للمستشفى..."
                                class="w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all p-4 min-h-[80px] resize-none focus:outline-none"
                            ></textarea>
                        </div>
                    </div>

                    <!-- فاصل -->
                    <div class="md:col-span-2 border-t border-gray-200 my-2"></div>

                    <!-- مدير المستشفى -->
                    <div class="space-y-2">
                        <Label for="manager" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            مدير المستشفى
                        </Label>
                        <div class="relative">
                            <select
                                id="manager"
                                v-model="form.managerId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">اختياري - بدون مدير</option>
                                <option v-for="manager in props.availableManagers" 
                                        :key="manager.id" 
                                        :value="manager.id">
                                    {{ manager.name }} - {{ manager.email || 'لا يوجد بريد' }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="form.managerId" class="text-xs text-gray-500 mt-1">
                            المدير المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ props.availableManagers.find(m => m.id === form.managerId)?.name }}
                            </span>
                        </p>
                    </div>

                    <!-- المورد -->
                    <div class="space-y-2">
                        <Label for="supplier" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المورد (اختياري)
                        </Label>
                        <div class="relative">
                            <select
                                id="supplier"
                                v-model="form.supplierId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">اختياري - بدون مورد</option>
                                <option v-for="supplier in props.availableSuppliers" 
                                        :key="supplier.id" 
                                        :value="supplier.id">
                                    {{ supplier.name }} - {{ supplier.id }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="form.supplierId" class="text-xs text-gray-500 mt-1">
                            المورد المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ props.availableSuppliers.find(s => s.id === form.supplierId)?.name }}
                            </span>
                        </p>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid"
                        :class="[
                            'px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200',
                            isFormValid 
                                ? 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1' 
                                : 'bg-gray-300 cursor-not-allowed shadow-none'
                        ]"
                    >
                        <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                        حفظ المستشفى
                    </button>
                </div>
            </form>
        </div>
    </div>

   <!-- نافذة التأكيد -->
<div v-if="isConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeConfirmationModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
        <div class="p-6 text-center space-y-4">
            <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <Icon
                    icon="solar:shield-warning-bold-duotone"
                    class="w-10 h-10 text-[#4DA1A9]"
                />
            </div>
            
            <h3 class="text-xl font-bold text-[#2E5077]">
                تأكيد إضافة المستشفى
            </h3>
            
            <p class="text-gray-500 leading-relaxed">
                هل أنت متأكد من رغبتك في إنشاء المستشفى الجديد باسم
                <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
                <br>
                <span class="text-sm text-[#4DA1A9]">سيتم إنشاء مستشفى جديد في النظام</span>
            </p>
        </div>
        
        <div class="flex justify-center bg-gray-50 px-6 py-4 gap-3 border-t border-gray-100">
            <button
                @click="closeConfirmationModal"
                class="px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
            >
                إلغاء
            </button>
            <button
                @click="confirmRegistration"
                class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
            >
                تأكيد الإضافة
            </button>
        </div>
    </div>
</div>
</template>