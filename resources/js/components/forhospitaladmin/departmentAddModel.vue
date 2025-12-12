<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    name: "",
    code: "",
    description: "",
    managerId: "",
});

// أخطاء التحقق
const errors = ref({
    name: false,
    code: false,
    description: false,
    managerId: false,
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// إعادة تعيين النموذج
const resetForm = () => {
    form.value = { 
        name: "", 
        code: "", 
        description: "",
        managerId: "",
    };
    errors.value = { 
        name: false, 
        code: false, 
        description: false,
        managerId: false
    };
};

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    errors.value.name = !data.name || data.name.trim().length < 2;
    if (errors.value.name) isValid = false;

    errors.value.code = !data.code || data.code.trim().length < 2;
    if (errors.value.code) isValid = false;

    errors.value.description = !data.description || data.description.trim().length < 5;
    if (errors.value.description) isValid = false;

    // مدير القسم اختياري
    errors.value.managerId = false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.code || data.code.trim().length < 2) return false;
    if (!data.description || data.description.trim().length < 5) return false;
    
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
    // البحث عن اسم المدير إذا تم اختياره
    let managerName = "";
    if (form.value.managerId) {
        const selectedManager = props.availableManagers.find(
            emp => emp.id === form.value.managerId || emp.fileNumber === form.value.managerId
        );
        managerName = selectedManager ? selectedManager.name : "";
    }
    
    const newDepartment = {
        name: form.value.name,
        code: form.value.code,
        description: form.value.description,
        managerId: form.value.managerId || null,
        managerName: managerName,
    };
    
    emit('save', newDepartment);
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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:buildings-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    إضافة قسم جديد
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات القسم الأساسية
                    </h3>

                    <div class="space-y-6">
                        <!-- اسم القسم -->
                        <div class="space-y-2">
                            <Label for="name" class="text-gray-700 font-bold">اسم القسم</Label>
                            <div class="relative">
                                <Input
                                    id="name"
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

                        <!-- رمز القسم -->
                        <div class="space-y-2">
                            <Label for="code" class="text-gray-700 font-bold">رمز القسم</Label>
                            <div class="relative">
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    placeholder="أدخل رمز القسم (مثال: ER, ICU)"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.code, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.code }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.code" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.code" class="text-sm text-red-500 font-medium">الرجاء إدخال رمز القسم.</p>
                        </div>

                        <!-- وصف القسم -->
                        <div class="space-y-2">
                            <Label for="description" class="text-gray-700 font-bold">وصف القسم</Label>
                            <div class="relative">
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="أدخل وصفاً مختصراً لمهام القسم..."
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.description, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.description }"
                                    class="w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all p-4 min-h-[100px] resize-none focus:outline-none"
                                ></textarea>
                                <Icon v-if="errors.description" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.description" class="text-sm text-red-500 font-medium">الرجاء إدخال وصف القسم (على الأقل 5 أحرف).</p>
                        </div>

                        <!-- مدير القسم -->
                        <div class="space-y-2">
                            <Label for="manager" class="text-gray-700 font-bold">مدير القسم</Label>
                            <div class="relative">
                                <select
                                    id="manager"
                                    v-model="form.managerId"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.managerId, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.managerId }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="">اختياري - بدون مدير</option>
                                    <option v-for="manager in props.availableManagers" 
                                            :key="manager.id || manager.fileNumber" 
                                            :value="manager.id || manager.fileNumber">
                                        {{ manager.name }} - {{ manager.id || manager.fileNumber }}
                                    </option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5 pointer-events-none" />
                            </div>
                            <p v-if="errors.managerId" class="text-sm text-red-500 font-medium">الرجاء اختيار مدير للقسم.</p>
                        </div>

                        <!-- ملاحظة -->
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
                            <Icon icon="solar:info-circle-bold" class="w-6 h-6 text-blue-600 flex-shrink-0" />
                            <p class="text-sm text-blue-700 leading-relaxed">
                                <span class="font-bold block mb-1">ملاحظة هامة:</span>
                                يمكن تعيين موظف واحد فقط كمدير لقسم. الموظفون المعروضون هم الموظفون النشطون وغير المعينين كمديرين لأقسام أخرى.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-6 py-3 rounded-xl text-[#2E5077] font-bold hover:bg-gray-100 transition-all duration-200"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid"
                        class="px-8 py-3 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                        حفظ القسم
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div
        v-if="isConfirmationModalOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    >
        <div
            @click="closeConfirmationModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 text-center rtl z-[70] transform transition-all scale-100"
        >
            <div class="w-20 h-20 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <Icon
                    icon="solar:shield-warning-bold-duotone"
                    class="w-10 h-10 text-yellow-500"
                />
            </div>
            
            <h3 class="text-xl font-bold text-[#2E5077] mb-3">
                تأكيد إضافة القسم
            </h3>
            
            <p class="text-gray-600 mb-8 leading-relaxed">
                هل أنت متأكد من رغبتك في إنشاء القسم الجديد باسم <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
            </p>
            
            <div class="flex gap-3 justify-center">
                <button
                    @click="closeConfirmationModal"
                    class="px-6 py-2.5 rounded-xl text-gray-600 font-bold hover:bg-gray-100 transition-all duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmRegistration"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
                >
                    تأكيد الإضافة
                </button>
            </div>
        </div>
    </div>
</template>