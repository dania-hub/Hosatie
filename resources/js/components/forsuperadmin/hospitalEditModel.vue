<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    hospital: Object,
    availableSuppliers: Array,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const editForm = ref({
   id: "",
  name: "",
 
  address: "",
  city: "",
  region: "",
  phone: "",

  managerId: "",
  supplierId: "",
  isActive: true,
});

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

// أخطاء التحقق
const editErrors = ref({
    name: false,
    city: false,
    phone: false,
});

// حالة نافذة التأكيد
const isEditConfirmationModalOpen = ref(false);

// التحقق من صحة النموذج
const validateEditForm = () => {
    let isValid = true;
    const data = editForm.value;

    editErrors.value.name = !data.name || data.name.trim().length < 2;
    if (editErrors.value.name) isValid = false;

    editErrors.value.city = !data.city || data.city.trim().length < 2;
    if (editErrors.value.city) isValid = false;

    // رقم الهاتف اختياري ولكن إذا تم إدخاله يجب أن يكون صالحاً
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^[\d\s\+\-\(\)]+$/;
        editErrors.value.phone = !phoneRegex.test(data.phone);
        if (editErrors.value.phone) isValid = false;
    } else {
        editErrors.value.phone = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isEditFormValid = computed(() => {
    const data = editForm.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.city || data.city.trim().length < 2) return false;
    
    return true;
});

// التحقق من وجود تغييرات
const isEditFormModified = computed(() => {
    const current = editForm.value;
    const original = originalEditForm.value;

    if (!original.id) return false;

    if (current.name !== original.name) return true;
   
    if (current.address !== original.address) return true;
    if (current.city !== original.city) return true;
    if (current.region !== original.region) return true;
    if (current.phone !== original.phone) return true;
    
    if (current.managerId !== original.managerId) return true;
    if (current.supplierId !== original.supplierId) return true;
    if (current.isActive !== original.isActive) return true;

    return false;
});

// إرسال النموذج
const submitEdit = () => {
    if (validateEditForm() && isEditFormModified.value) {
        isEditConfirmationModalOpen.value = true;
    }
};

// تأكيد التعديل
const confirmEdit = () => {
    // البحث عن اسم المورد إذا تم اختياره
    let supplierName = "";
    if (editForm.value.supplierId) {
        const selectedSupplier = props.availableSuppliers.find(
            supplier => supplier.id === editForm.value.supplierId
        );
        supplierName = selectedSupplier ? selectedSupplier.name : "";
    }

    // البحث عن اسم المدير إذا تم اختياره
    let managerName = "";
    let managerEmail = "";
    let managerPhone = "";
    if (editForm.value.managerId) {
        const selectedManager = props.availableManagers.find(
            manager => manager.id === editForm.value.managerId
        );
        if (selectedManager) {
            managerName = selectedManager.name || "";
            managerEmail = selectedManager.email || "";
            managerPhone = selectedManager.phone || "";
        }
    }
    
    const updatedHospital = {
        id: editForm.value.id,
        name: editForm.value.name,
       
        address: editForm.value.address,
        city: editForm.value.city,
        region: editForm.value.region,
        phone: editForm.value.phone,
      
        managerId: editForm.value.managerId || null,
        managerName: managerName,
        managerEmail: managerEmail,
        managerPhone: managerPhone,
        supplierId: editForm.value.supplierId || null,
        supplierName: supplierName,
        isActive: editForm.value.isActive,
    };
    
    emit('save', updatedHospital);
    closeEditConfirmationModal();
    closeEditModal();
};

// إغلاق النافذة الرئيسية
const closeEditModal = () => {
    emit('close');
};

// إغلاق نافذة التأكيد
const closeEditConfirmationModal = () => {
    isEditConfirmationModalOpen.value = false;
};

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.hospital) {
        const initialData = {
            id: props.hospital.id,
            name: props.hospital.name || "",
            code: props.hospital.code || "",
            address: props.hospital.address || "",
            city: props.hospital.city || "",
            region: props.hospital.region || "",
            phone: props.hospital.phone || "",
            email: props.hospital.email || "",
            managerId: props.hospital.managerId || "",
            supplierId: props.hospital.supplierId || "",
            isActive: props.hospital.isActive !== undefined ? props.hospital.isActive : true,
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            name: false,
            city: false,
            phone: false,
        };
    }
});
</script>

<template>
    <!-- Modal الرئيسي -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[90] flex items-center justify-center p-4"
    >
        <div
            @click="closeEditModal"
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
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات المستشفى
                </h2>
                <button @click="closeEditModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitEdit" class="p-8 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- اسم المستشفى -->
                    <div class="space-y-2">
                        <Label for="edit-name" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المستشفى
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-name"
                                v-model="editForm.name"
                                placeholder="أدخل اسم المستشفى"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.name }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="editErrors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المستشفى (على الأقل حرفين)
                        </p>
                    </div>

                    
                    <!-- المدينة -->
                    <div class="space-y-2">
                        <Label for="edit-city" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المدينة
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-city"
                                v-model="editForm.city"
                                placeholder="أدخل اسم المدينة"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.city, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.city }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="editErrors.city" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المدينة
                        </p>
                    </div>

                    <!-- المنطقة -->
                    <div class="space-y-2">
                        <Label for="edit-region" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المنطقة (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-region"
                                v-model="editForm.region"
                                placeholder="أدخل اسم المنطقة"
                                type="text"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                    </div>

                    <!-- رقم الهاتف -->
                    <div class="space-y-2">
                        <Label for="edit-phone" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="edit-phone"
                                v-model="editForm.phone"
                                placeholder="مثال: 05XXXXXXXX"
                                type="tel"
                                :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.phone, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.phone }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="editErrors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            رقم الهاتف غير صالح
                        </p>
                    </div>

                   

                    <!-- العنوان -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="edit-address" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            العنوان التفصيلي (اختياري)
                        </Label>
                        <div class="relative">
                            <textarea
                                id="edit-address"
                                v-model="editForm.address"
                                placeholder="أدخل العنوان التفصيلي للمستشفى..."
                                class="w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all p-4 min-h-[80px] resize-none focus:outline-none"
                            ></textarea>
                        </div>
                    </div>

                    <!-- فاصل -->
                    <div class="md:col-span-2 border-t border-gray-200 my-2"></div>

                    <!-- مدير المستشفى -->
                    <div class="space-y-2">
                        <Label for="edit-manager" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            مدير المستشفى
                        </Label>
                        <div class="relative">
                            <select
                                id="edit-manager"
                                v-model="editForm.managerId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">بدون مدير</option>
                                <option v-for="manager in props.availableManagers" 
                                        :key="manager.id" 
                                        :value="manager.id">
                                    {{ manager.name }} - {{ manager.email || 'لا يوجد بريد' }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="editForm.managerId" class="text-xs text-gray-500 mt-1">
                            المدير المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ props.availableManagers.find(m => m.id === editForm.managerId)?.name }}
                            </span>
                        </p>
                    </div>

                    <!-- المورد -->
                    <div class="space-y-2">
                        <Label for="edit-supplier" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المورد (اختياري)
                        </Label>
                        <div class="relative">
                            <select
                                id="edit-supplier"
                                v-model="editForm.supplierId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">بدون مورد</option>
                                <option v-for="supplier in props.availableSuppliers" 
                                        :key="supplier.id" 
                                        :value="supplier.id">
                                    {{ supplier.name }} - {{ supplier.id }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="editForm.supplierId" class="text-xs text-gray-500 mt-1">
                            المورد المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ props.availableSuppliers.find(s => s.id === editForm.supplierId)?.name }}
                            </span>
                        </p>
                    </div>

                    <!-- حالة المستشفى -->
                    <div class="space-y-2">
                        <Label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:shield-check-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة المستشفى
                        </Label>
                        <div class="flex gap-4 p-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative flex items-center justify-center">
                                    <input 
                                        type="radio" 
                                        v-model="editForm.isActive" 
                                        :value="true"
                                        class="peer sr-only"
                                    />
                                    <div class="w-6 h-6 border-2 border-gray-300 rounded-full peer-checked:border-[#4DA1A9] peer-checked:bg-[#4DA1A9] transition-all"></div>
                                    <Icon icon="solar:check-circle-bold" class="w-4 h-4 text-white absolute opacity-0 peer-checked:opacity-100 transition-all" />
                                </div>
                                <span class="text-gray-700 font-medium group-hover:text-[#4DA1A9] transition-colors">مفعل</span>
                            </label>

                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative flex items-center justify-center">
                                    <input 
                                        type="radio" 
                                        v-model="editForm.isActive" 
                                        :value="false"
                                        class="peer sr-only"
                                    />
                                    <div class="w-6 h-6 border-2 border-gray-300 rounded-full peer-checked:border-red-500 peer-checked:bg-red-500 transition-all"></div>
                                    <Icon icon="solar:close-circle-bold" class="w-4 h-4 text-white absolute opacity-0 peer-checked:opacity-100 transition-all" />
                                </div>
                                <span class="text-gray-700 font-medium group-hover:text-red-500 transition-colors">معطل</span>
                            </label>
                        </div>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isEditFormModified || !isEditFormValid"
                        class="px-8 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:shadow-xl hover:-translate-y-1 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تأكيد التعديل -->
<div v-if="isEditConfirmationModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeEditConfirmationModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
        <div class="p-6 text-center space-y-4">
            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <Icon
                    icon="solar:pen-new-square-bold-duotone"
                    class="w-10 h-10 text-[#4DA1A9]"
                />
            </div>
            
            <h3 class="text-xl font-bold text-[#2E5077]">
                تأكيد التعديل
            </h3>
            
            <p class="text-gray-500 leading-relaxed">
                هل أنت متأكد من رغبتك في حفظ التعديلات على المستشفى
                <span class="font-bold text-[#2E5077]">"{{ editForm.name }}"</span>؟
                <br>
                <span class="text-sm text-[#4DA1A9]">سيتم تحديث بيانات المستشفى في النظام</span>
            </p>
        </div>
        
        <div class="flex justify-center bg-gray-50 px-6 py-4 gap-3 border-t border-gray-100">
            <button
                @click="closeEditConfirmationModal"
                class="px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
            >
                إلغاء
            </button>
            <button
                @click="confirmEdit"
                class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
            >
                حفظ التعديلات
            </button>
        </div>
    </div>
</div>
</template>