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
    isActive: true,
});

// البيانات الأصلية للمقارنة
const originalForm = ref({});

// أخطاء التحقق
const errors = ref({
    name: false,
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    errors.value.name = !data.name || data.name.trim().length < 2;
    if (errors.value.name) isValid = false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    
    return true;
});

// التحقق من وجود تغييرات
const hasChanges = computed(() => {
    const current = form.value;
    const original = originalForm.value;

    if (!original.id) return false;

    if (current.name !== original.name) return true;
    if (current.managerId !== original.managerId) return true;
    if (current.isActive !== original.isActive) return true;

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
        managerId: form.value.managerId || null,
        isActive: form.value.isActive,
    };
    
    emit('save', updatedDepartment);
    closeConfirmationModal();
    emit('close');
};

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.department) {
        const initialData = {
            id: props.department.id,
            name: props.department.name || "",
            managerId: props.department.managerId || "",
            isActive: props.department.isActive !== undefined ? props.department.isActive : true,
        };

        originalForm.value = { ...initialData };
        form.value = initialData;

        // إعادة تعيين الأخطاء
        errors.value = {
            name: false,
        };
    }
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
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
            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                
                <!-- معلومات القسم -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات القسم
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- اسم القسم -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">اسم القسم</label>
                            <div class="relative">
                                <Input
                                    required
                                    v-model="form.name"
                                    placeholder="أدخل اسم القسم"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.name }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.name" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.name" class="text-sm text-red-500 font-medium">الرجاء إدخال اسم القسم (على الأقل حرفين).</p>
                        </div>

                        <!-- مدير القسم -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">مدير القسم (اختياري)</label>
                            <div class="relative">
                                <select
                                    v-model="form.managerId"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 border-gray-200 focus:border-[#4DA1A9] focus:ring-4 focus:ring-[#4DA1A9]/20 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="">بدون مدير</option>
                                    <option v-for="manager in props.availableManagers" 
                                            :key="manager.id || manager.fileNumber" 
                                            :value="manager.id || manager.fileNumber">
                                        {{ manager.name || manager.full_name || manager.nameDisplay }} - {{ manager.id || manager.fileNumber }}
                                    </option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5 pointer-events-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- حالة القسم -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:settings-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        حالة القسم
                    </h3>

                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-gray-500 block mb-2">حالة القسم</label>
                        <div class="flex gap-4">
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="form.isActive" :value="true" class="peer sr-only" />
                                <div class="px-6 py-3 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 transition-all flex items-center gap-2">
                                    <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                                    <span class="font-bold">مفعل</span>
                                </div>
                            </label>
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="form.isActive" :value="false" class="peer sr-only" />
                                <div class="px-6 py-3 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 transition-all flex items-center gap-2">
                                    <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                    <span class="font-bold">معطل</span>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            {{ form.isActive ? 'القسم نشط ويمكن استخدامه' : 'القسم معطل ولن يظهر في القوائم' }}
                        </p>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="submitForm"
                    :disabled="!isFormValid || !hasChanges"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                    حفظ التعديلات
                </button>
            </div>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div v-if="isConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:pen-new-square-bold-duotone" class="w-8 h-8 text-yellow-500" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد التعديل</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في حفظ التعديلات على القسم <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
                </p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeConfirmationModal" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmUpdate" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20"
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
