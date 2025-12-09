<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    department: Object,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const editForm = ref({
    id: "",
    name: "",
    code: "",
    description: "",
    managerId: "",
    isActive: true,
});

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

// أخطاء التحقق
const editErrors = ref({
    name: false,
    code: false,
    description: false,
    managerId: false,
});

// حالة نافذة التأكيد
const isEditConfirmationModalOpen = ref(false);

// التحقق من صحة النموذج
const validateEditForm = () => {
    let isValid = true;
    const data = editForm.value;

    editErrors.value.name = !data.name || data.name.trim().length < 2;
    if (editErrors.value.name) isValid = false;

    editErrors.value.code = !data.code || data.code.trim().length < 2;
    if (editErrors.value.code) isValid = false;

    editErrors.value.description = !data.description || data.description.trim().length < 5;
    if (editErrors.value.description) isValid = false;

    // مدير القسم اختياري
    editErrors.value.managerId = false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isEditFormValid = computed(() => {
    const data = editForm.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.code || data.code.trim().length < 2) return false;
    if (!data.description || data.description.trim().length < 5) return false;
    
    return true;
});

// التحقق من وجود تغييرات
const isEditFormModified = computed(() => {
    const current = editForm.value;
    const original = originalEditForm.value;

    if (!original.id) return false;

    if (current.name !== original.name) return true;
    if (current.code !== original.code) return true;
    if (current.description !== original.description) return true;
    if (current.managerId !== original.managerId) return true;
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
    // البحث عن اسم المدير إذا تم اختياره
    let managerName = "";
    if (editForm.value.managerId) {
        const selectedManager = props.availableManagers.find(
            emp => emp.fileNumber === editForm.value.managerId
        );
        managerName = selectedManager ? selectedManager.name : "";
    }
    
    const updatedDepartment = {
        id: editForm.value.id,
        name: editForm.value.name,
        code: editForm.value.code,
        description: editForm.value.description,
        managerId: editForm.value.managerId || null,
        managerName: managerName,
        isActive: editForm.value.isActive,
    };
    
    emit('save', updatedDepartment);
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
    if (newVal && props.department) {
        const initialData = {
            id: props.department.id,
            name: props.department.name || "",
            code: props.department.code || "",
            description: props.department.description || "",
            managerId: props.department.managerId || "",
            isActive: props.department.isActive !== undefined ? props.department.isActive : true,
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            name: false,
            code: false,
            description: false,
            managerId: false,
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
                    نموذج تعديل بيانات القسم
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
                        معلومات القسم الأساسية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="edit-name" class="text-right font-medium text-[#2E5077] pt-2">اسم القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    id="edit-name"
                                    v-model="editForm.name"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.name, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.name }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="editErrors.name" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.name" class="text-xs text-red-500 mt-1">الرجاء إدخال اسم القسم (على الأقل حرفين).</p>
                            </div>
                        </div>

                        

            

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="edit-manager" class="text-right font-medium text-[#2E5077] pt-2">مدير القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    id="edit-manager"
                                    v-model="editForm.managerId"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.managerId, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.managerId }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="">بدون مدير</option>
                                    <option v-for="manager in props.availableManagers" 
                                            :key="manager.fileNumber" 
                                            :value="manager.fileNumber">
                                        {{ manager.name }} - {{ manager.fileNumber }}
                                    </option>
                                </select>
                                <Icon v-if="editErrors.managerId" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.managerId" class="text-xs text-red-500 mt-1">الرجاء اختيار مدير للقسم.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <Label for="edit-isActive" class="text-right font-medium text-[#2E5077] pt-2">حالة القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <div class="flex items-center gap-3 mt-2">
                                    <label class="flex items-center cursor-pointer">
                                        <input 
                                            type="radio" 
                                            v-model="editForm.isActive" 
                                            :value="true"
                                            class="mr-2"
                                        />
                                        <span>مفعل</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input 
                                            type="radio" 
                                            v-model="editForm.isActive" 
                                            :value="false"
                                            class="mr-2"
                                        />
                                        <span>معطل</span>
                                    </label>
                                </div>
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
                            class=" inline-flex items-center px-[13px] py-[9px] border-2 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1]"
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
                    تعديل بيانات القسم
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في حفظ التعديلات على القسم "{{ editForm.name }}"؟
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="confirmEdit"
                        class=" inline-flex items-center px-[25px] py-[9px] border-2 border-[#ffffff8d] 
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