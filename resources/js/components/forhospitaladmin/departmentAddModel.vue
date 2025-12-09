<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import btnform from "@/components/btnform.vue";
import Btncancel from "@/components/btncancel.vue";

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
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 " 
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        ></div>

         <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full sm:w-150 max-w-[95vw] sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon
                        icon="mingcute:department-add-line"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج إنشاء قسم جديد
                </h2>

                <Button
                    @click="closeModal"
                    variant="ghost"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </Button>
            </div>

            <form @submit.prevent="submitForm" class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1">
                        معلومات القسم الأساسية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="name" class="text-right font-medium text-[#2E5077] pt-2">اسم القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="name"
                                    v-model="form.name"
                                    placeholder="أدخل اسم القسم"
                                    type="text"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.name, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9] ': !errors.name }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="errors.name" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.name" class="text-xs text-red-500 mt-1">الرجاء إدخال اسم القسم (على الأقل حرفين).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="code" class="text-right font-medium text-[#2E5077] pt-2">رمز القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="code"
                                    v-model="form.code"
                                    placeholder="أدخل رمز القسم"
                                    type="text"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.code, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.code }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="errors.code" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.code" class="text-xs text-red-500 mt-1">الرجاء إدخال رمز القسم.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="description" class="text-right font-medium text-[#2E5077] pt-2">وصف القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="أدخل وصف القسم"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.description, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.description }"
                                    class="h-24 text-right w-full rounded-2xl bg-white px-3 py-2 border focus:outline-none resize-none"
                                    rows="3"
                                ></textarea>
                                <Icon v-if="errors.description" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.description" class="text-xs text-red-500 mt-1">الرجاء إدخال وصف القسم (على الأقل 5 أحرف).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="manager" class="text-right font-medium text-[#2E5077] pt-2">مدير القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    id="manager"
                                    v-model="form.managerId"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.managerId, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.managerId }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="">اختياري - بدون مدير</option>
                                    <option v-for="manager in props.availableManagers" 
                                            :key="manager.id || manager.fileNumber" 
                                            :value="manager.id || manager.fileNumber">
                                        {{ manager.name }} - {{ manager.id || manager.fileNumber }}
                                    </option>
                                </select>
                                <Icon v-if="errors.managerId" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.managerId" class="text-xs text-red-500 mt-1">الرجاء اختيار مدير للقسم.</p>
                            </div>
                        </div>

                        <!-- ملاحظة حول مدراء الأقسام -->
                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2"></Label>
                            <div class="relative w-full sm:w-75">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                    <Icon icon="tabler:info-circle" class="w-4 h-4 inline ml-1" />
                                    <span class="font-semibold">ملاحظة:</span> يمكن تعيين موظف واحد فقط كمدير لقسم. الموظفون المعروضون هم الموظفون النشطون وغير المعينين كمديرين لأقسام أخرى.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center">
                    <div class="flex gap-3 pb-4">
                        <btnform :disabled="!isFormValid" />
                        <Btncancel @cancel="closeModal" />
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div
        v-if="isConfirmationModalOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeConfirmationModal"
            class="absolute inset-0 bg-black/30 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[70] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col items-center">
                <Icon
                    icon="tabler:alert-triangle-filled"
                    class="w-16 h-16 text-yellow-500 mb-4"
                />
                <p class="text-xl font-bold text-[#2E5077] mb-3">
                    إنشاء قسم جديد
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في إنشاء القسم "{{ form.name }}"؟
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="confirmRegistration"
                        class="bg-[#4DA1A9] text-white font-semibold py-2 px-6 rounded-full hover:bg-[#3a8c94] transition-colors duration-200"
                    >
                        تأكيد
                    </button>
                    <button
                        @click="closeConfirmationModal"
                        class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>