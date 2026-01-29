<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean,
    department: Object,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    id: "",
    name: "",
    managerId: "",
});

// البيانات الأصلية للمقارنة
const originalForm = ref({});

// أخطاء التحقق
const errors = ref({
    name: false,
    nameMessage: "",
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// منع إدخال الأرقام في اسم القسم
const handleNameInput = (event) => {
    const value = event.target.value;
    // إزالة أي أرقام من النص
    const cleanedValue = value.replace(/[0-9]/g, '');
    form.value.name = cleanedValue;
};

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;
    const trimmedName = data.name.trim();

    // التحقق من وجود الاسم
    if (!trimmedName || trimmedName.length < 2) {
        errors.value.name = true;
        errors.value.nameMessage = "الرجاء إدخال اسم القسم (على الأقل حرفين)";
        isValid = false;
    }
    // التحقق من أن الاسم يبدأ بـ "قسم"
    else if (!trimmedName.startsWith('قسم')) {
        errors.value.name = true;
        errors.value.nameMessage = "يجب أن يبدأ اسم القسم بكلمة 'قسم'";
        isValid = false;
    }
    // التحقق من وجود نص بعد "قسم" (أكثر من مجرد كلمة "قسم" فقط)
    else if (trimmedName === 'قسم' || trimmedName.trim() === 'قسم') {
        errors.value.name = true;
        errors.value.nameMessage = "يجب كتابة اسم القسم بعد كلمة 'قسم' (مثال: قسم الأطفال)";
        isValid = false;
    }
    // التحقق من عدم وجود أرقام
    else if (/[0-9]/.test(trimmedName)) {
        errors.value.name = true;
        errors.value.nameMessage = "لا يمكن إدخال أرقام في اسم القسم";
        isValid = false;
    }
    else {
        errors.value.name = false;
        errors.value.nameMessage = "";
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    const trimmedName = data.name.trim();
    
    if (!trimmedName || trimmedName.length < 2) return false;
    if (!trimmedName.startsWith('قسم')) return false;
    if (trimmedName === 'قسم' || trimmedName.trim() === 'قسم') return false;
    if (/[0-9]/.test(trimmedName)) return false;
    
    return true;
});

// التحقق من وجود تغييرات
const hasChanges = computed(() => {
    const current = form.value;
    const original = originalForm.value;

    if (!original.id) return false;

    if (current.name !== original.name) return true;
    if (current.managerId !== original.managerId) return true;

    return false;
});

// إرسال النموذج
const submitForm = () => {
    if (validateForm() && hasChanges.value) {
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

// تأكيد التعديل
const confirmUpdate = () => {
    const updatedDepartment = {
        id: form.value.id,
        name: form.value.name,
        managerId: form.value.managerId ? (Number(form.value.managerId) || form.value.managerId) : null,
    };
    
    emit('save', updatedDepartment);
    closeConfirmationModal();
    emit('close');
};

// حساب المدير الحالي
const currentManager = computed(() => {
    if (!form.value.managerId) return null;
    return props.availableManagers.find(m => 
        String(m.id || m.fileNumber) === String(form.value.managerId)
    ) || (props.department?.managerName ? {
        id: props.department.managerId,
        name: props.department.managerName,
        full_name: props.department.managerName
    } : null);
});

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.department) {
        // تحويل managerId إلى string للتأكد من المطابقة
        const managerId = props.department.managerId 
            ? String(props.department.managerId) 
            : "";
        
        const initialData = {
            id: props.department.id,
            name: props.department.name || "",
            managerId: managerId,
        };

        originalForm.value = { ...initialData };
        form.value = initialData;

        // إعادة تعيين الأخطاء
        errors.value = {
            name: false,
            nameMessage: "",
        };
    }
});

// إضافة المدير الحالي إلى قائمة المتاحين إذا لم يكن موجوداً
const managersWithCurrent = computed(() => {
    const managers = [...props.availableManagers];
    
    if (form.value.managerId && props.department?.managerName) {
        const exists = managers.some(m => 
            String(m.id || m.fileNumber) === String(form.value.managerId)
        );
        
        if (!exists) {
            managers.unshift({
                id: props.department.managerId,
                fileNumber: props.department.managerId,
                name: props.department.managerName,
                full_name: props.department.managerName,
                nameDisplay: props.department.managerName
            });
        }
    }
    
    return managers;
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100" dir="rtl">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات القسم
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8 overflow-y-auto max-h-[70vh]">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- اسم القسم -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:case-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم القسم
                        </label>
                        <Input
                            id="name"
                            v-model="form.name"
                            @input="handleNameInput"
                            placeholder="قسم ... (مثال: قسم الأطفال)"
                            :class="[
                                'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20',
                                errors.name ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                            ]"
                        />
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errors.nameMessage || "الرجاء إدخال اسم القسم بشكل صحيح" }}
                        </p>
                        <p v-else class="text-xs text-gray-500">
                            يجب أن يبدأ اسم القسم بكلمة "قسم" ولا يحتوي على أرقام
                        </p>
                    </div>

                    <!-- مدير القسم -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            مدير القسم (اختياري)
                        </label>
                        <div class="relative">
                            <select
                                id="managerId"
                                v-model="form.managerId"
                                class="w-full h-11 px-4 pr-10 rounded-2xl bg-white border border-gray-200 appearance-none focus:outline-none focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9]/20 transition-colors duration-200"
                            >
                                <option value="">بدون مدير</option>
                                <option v-for="manager in managersWithCurrent" 
                                        :key="manager.id || manager.fileNumber" 
                                        :value="String(manager.id || manager.fileNumber)">
                                    {{ manager.name || manager.full_name || manager.nameDisplay }} - {{ manager.id || manager.fileNumber }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3 pointer-events-none" />
                        </div>
                        <p class="text-xs text-gray-500">
                            المدير الحالي: 
                            <span class="font-semibold text-[#2E5077]">
                                {{ currentManager ? (currentManager.name || currentManager.full_name || currentManager.nameDisplay) : 'غير محدد' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                >
                    إلغاء
                </button>
                <button 
                    @click="submitForm" 
                    :disabled="!isFormValid || !hasChanges"
                    :class="[
                        'px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200',
                        (isFormValid && hasChanges) 
                            ? 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-0.5' 
                            : 'bg-gray-300 cursor-not-allowed shadow-none'
                    ]"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    حفظ التعديلات
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="isConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:question-circle-bold-duotone" class="w-10 h-10 text-[#4DA1A9]" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد التعديل</h3>
                <p class="text-gray-500 leading-relaxed text-center">
                    هل أنت متأكد من رغبتك في حفظ التعديلات على القسم <br>
                    <span class="font-bold text-[#2E5077] text-lg">"{{ form.name }}"</span>؟
                </p>
            </div>
            <div class="flex justify-center bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeConfirmationModal" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmUpdate" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
                >
                    تأكيد الحفظ
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
