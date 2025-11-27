<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const editForm = ref({
    fileNumber: "",
    nationalId: "",
    fullName: "",
    birthDate: "",
    phone: "",
    email: "",
});

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

// أخطاء التحقق
const editErrors = ref({
    birthDate: false,
    phone: false,
    email: false,
});

// حالة نافذة التأكيد
const isEditConfirmationModalOpen = ref(false);

// التحقق من صحة النموذج
const validateEditForm = () => {
    let isValid = true;
    const data = editForm.value;

    editErrors.value.birthDate = !data.birthDate;
    if (editErrors.value.birthDate) isValid = false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/; 
    editErrors.value.phone = !phoneRegex.test(data.phone.trim());
    if (editErrors.value.phone) isValid = false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    editErrors.value.email = !emailRegex.test(data.email.trim());
    if (editErrors.value.email) isValid = false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isEditFormValid = computed(() => {
    const data = editForm.value;
    
    if (!data.birthDate) return false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/; 
    if (!phoneRegex.test(data.phone.trim())) return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email.trim())) return false;

    return true;
});

// التحقق من وجود تغييرات
const isEditFormModified = computed(() => {
    const current = editForm.value;
    const original = originalEditForm.value;

    if (!original.fileNumber) return false;

    if (current.birthDate !== original.birthDate) return true;
    if (current.phone !== original.phone) return true;
    if (current.email !== original.email) return true;

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
    const updatedPatient = {
        fileNumber: editForm.value.fileNumber,
        name: editForm.value.fullName,
        nationalId: editForm.value.nationalId,
        birth: editForm.value.birthDate.replace(/-/g, "/"),
        phone: editForm.value.phone,
        email: editForm.value.email,
    };
    
    emit('save', updatedPatient);
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
    if (newVal && props.patient) {
        const formattedBirthDate = props.patient.birth ? props.patient.birth.replace(/\//g, "-") : '';

        const initialData = {
            fileNumber: props.patient.fileNumber,
            nationalId: props.patient.nationalId,
            fullName: props.patient.name,
            birthDate: formattedBirthDate,
            phone: props.patient.phone,
            email: props.patient.email || "alimohmed@example.com",
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            birthDate: false,
            phone: false,
            email: false,
        };
    }
});
</script>

<template>
    <!-- Modal الرئيسي -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[90] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeEditModal"
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
                        icon="tabler:edit"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج تعديل البيانات
                </h2>

                <Button
                    @click="closeEditModal"
                    variant="ghost"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </Button>
            </div>

            <form @submit.prevent="submitEdit" class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1">
                        المعلومات الشخصية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الرقم الوطني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    v-model="editForm.nationalId"
                                    class="h-9 text-right rounded-2xl w-full border-gray-300 bg-gray-200 text-gray-600 cursor-not-allowed focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2">الإسم رباعي</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    readonly
                                    v-model="editForm.fullName"
                                    class="h-9 text-right rounded-2xl w-full border-gray-300 bg-gray-200 text-gray-600 cursor-not-allowed focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-birth" class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    id="edit-birth"
                                    type="date" 
                                    v-model="editForm.birthDate"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.birthDate, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.birthDate }"
                                    class="h-9 text-right w-full pr-3 appearance-none rounded-2xl bg-white"
                                />
                                <Icon v-if="editErrors.birthDate" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.birthDate" class="text-xs text-red-500 mt-1">الرجاء تحديد تاريخ الميلاد.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1">
                        معلومات الإتصال
                    </h3>
                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-phone" class="text-right font-medium text-[#2E5077] pt-2">رقم الهاتف</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    id="edit-phone"
                                    v-model="editForm.phone"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.phone, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.phone }"
                                    class="h-9 text-right rounded-2xl w-full bg-white"
                                />
                                <Icon v-if="editErrors.phone" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.phone" class="text-xs text-red-500 mt-1">تأكد من إدخال رقم هاتف صحيح (مثال: 091xxxxxxx).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-email" class="text-right font-medium text-[#2E5077] pt-2">البريد الإلكتروني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    id="edit-email"
                                    v-model="editForm.email"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.email, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.email }"
                                    class="h-9 text-right rounded-2xl w-full bg-white"
                                />
                                <Icon v-if="editErrors.email" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.email" class="text-xs text-red-500 mt-1">تأكد من إدخال البريد الإلكتروني صحيح.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center mt-6">
                    <div class="flex gap-3 pb-4">
                        <button
                            type="submit"
                            :disabled="!isEditFormModified || !isEditFormValid"
                            :class="{
                                'bg-[#4DA1A9] hover:bg-[#3a8c94] border-[#ffffff8d]': isEditFormModified && isEditFormValid,
                                'bg-gray-400 border-[#a8a8a8] text-gray-100 cursor-not-allowed': !isEditFormModified || !isEditFormValid
                            }"
                            class="button inline-flex items-center px-[13px] py-[9px] border-2 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1]"
                        >
                            تأكيد التعديل
                        </button>
                        
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="inline-flex items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] 
                            transition-all duration-200 ease-in relative overflow-hidden text-[15px] 
                            cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                        >
                            إلغاء
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div
        v-if="isEditConfirmationModalOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeEditConfirmationModal"
            class="absolute inset-0 bg-black/30 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[110] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col items-center">
                <Icon
                    icon="tabler:alert-triangle-filled"
                    class="w-16 h-16 text-yellow-500 mb-4"
                />
                <p class="text-xl font-bold text-[#2E5077] mb-3">
                    لقد قمت بإدخال بعض التعديلات للبيانات.
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في الحفظ؟
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="confirmEdit"
                        class="button inline-flex items-center px-[25px] py-[9px] border-2 border-[#ffffff8d] 
                        rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden 
                        text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#3a8c94]"
                    >
                        تأكيد
                    </button>
                    <button
                        @click="closeEditConfirmationModal"
                        class="inline-flex items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] 
                        transition-all duration-200 ease-in relative overflow-hidden text-[15px] 
                        cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>