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
            emp => (emp.id === editForm.value.managerId) || (emp.fileNumber === editForm.value.managerId)
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
        class="fixed inset-0 z-[90] flex items-center justify-center p-4"
    >
        <div
            @click="closeEditModal"
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
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات القسم
                </h2>
                <button @click="closeEditModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitEdit" class="p-8 space-y-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات القسم
                    </h3>

                    <div class="space-y-6">
                        <!-- اسم القسم -->
                        <div class="space-y-2">
                            <Label for="edit-name" class="text-gray-700 font-bold">اسم القسم</Label>
                            <div class="relative">
                                <Input
                                    id="edit-name"
                                    v-model="editForm.name"
                                    placeholder="أدخل اسم القسم"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.name }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="editErrors.name" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="editErrors.name" class="text-sm text-red-500 font-medium">الرجاء إدخال اسم القسم (على الأقل حرفين).</p>
                        </div>

                        <!-- رمز القسم -->
                        <div class="space-y-2">
                            <Label for="edit-code" class="text-gray-700 font-bold">رمز القسم</Label>
                            <div class="relative">
                                <Input
                                    id="edit-code"
                                    v-model="editForm.code"
                                    placeholder="أدخل رمز القسم"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.code, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.code }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="editErrors.code" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="editErrors.code" class="text-sm text-red-500 font-medium">الرجاء إدخال رمز القسم.</p>
                        </div>

                        <!-- وصف القسم -->
                        <div class="space-y-2">
                            <Label for="edit-description" class="text-gray-700 font-bold">وصف القسم</Label>
                            <div class="relative">
                                <textarea
                                    id="edit-description"
                                    v-model="editForm.description"
                                    placeholder="أدخل وصف القسم"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.description, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.description }"
                                    class="w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all p-4 min-h-[100px] resize-none focus:outline-none"
                                ></textarea>
                                <Icon v-if="editErrors.description" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="editErrors.description" class="text-sm text-red-500 font-medium">الرجاء إدخال وصف القسم (على الأقل 5 أحرف).</p>
                        </div>

                        <!-- مدير القسم -->
                        <div class="space-y-2">
                            <Label for="edit-manager" class="text-gray-700 font-bold">مدير القسم</Label>
                            <div class="relative">
                                <select
                                    id="edit-manager"
                                    v-model="editForm.managerId"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': editErrors.managerId, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !editErrors.managerId }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="">بدون مدير</option>
                                    <option v-for="manager in props.availableManagers" 
                                            :key="manager.id || manager.fileNumber" 
                                            :value="manager.id || manager.fileNumber">
                                        {{ manager.name }} - {{ manager.id || manager.fileNumber }}
                                    </option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5 pointer-events-none" />
                            </div>
                            <p v-if="editErrors.managerId" class="text-sm text-red-500 font-medium">الرجاء اختيار مدير للقسم.</p>
                        </div>

                        <!-- حالة القسم -->
                        <div class="space-y-2">
                            <Label class="text-gray-700 font-bold">حالة القسم</Label>
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
                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="px-6 py-3 rounded-xl text-[#2E5077] font-bold hover:bg-gray-100 transition-all duration-200"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isEditFormModified || !isEditFormValid"
                        class="px-8 py-3 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                        حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div
        v-if="isEditConfirmationModalOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    >
        <div
            @click="closeEditConfirmationModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 text-center rtl z-[110] transform transition-all scale-100"
        >
            <div class="w-20 h-20 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <Icon
                    icon="solar:shield-warning-bold-duotone"
                    class="w-10 h-10 text-yellow-500"
                />
            </div>
            
            <h3 class="text-xl font-bold text-[#2E5077] mb-3">
                تأكيد التعديل
            </h3>
            
            <p class="text-gray-600 mb-8 leading-relaxed">
                هل أنت متأكد من رغبتك في حفظ التعديلات على القسم <span class="font-bold text-[#2E5077]">"{{ editForm.name }}"</span>؟
            </p>
            
            <div class="flex gap-3 justify-center">
                <button
                    @click="closeEditConfirmationModal"
                    class="px-6 py-2.5 rounded-xl text-gray-600 font-bold hover:bg-gray-100 transition-all duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmEdit"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
                >
                    تأكيد الحفظ
                </button>
            </div>
        </div>
    </div>
</template>