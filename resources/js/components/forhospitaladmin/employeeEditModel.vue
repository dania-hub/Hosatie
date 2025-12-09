<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    patient: Object,
    hasWarehouseManager: Boolean,
    availableDepartments: Array,
    availableRoles: Array,
    departmentsWithManager: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const editForm = ref({
    fileNumber: "",
    nationalId: "",
    name: "",
    birth: "",
    phone: "",
    email: "",
    role: "",
    department: "",
    isActive: true,
});

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

// أخطاء التحقق
const editErrors = ref({
    birth: false,
    phone: false,
    email: false,
    role: false,
    department: false,
});

// حالة نافذة التأكيد
const isEditConfirmationModalOpen = ref(false);

// الحصول على قائمة الأدوار بشكل صحيح
const getRoleList = computed(() => {
    if (!props.availableRoles) return [];
    
    // إذا كانت الأدوار عبارة عن كائنات تحتوي على حقل name
    if (props.availableRoles.length > 0 && typeof props.availableRoles[0] === 'object') {
        return props.availableRoles.map(role => role.name);
    }
    
    // إذا كانت الأدوار مجرد نصوص
    return props.availableRoles;
});

// الحصول على اسم الدور
const getRoleName = (role) => {
    if (!role) return "";
    
    if (typeof role === 'object') {
        return role.name || role;
    }
    
    return role;
};

// التحقق مما إذا كان الدور هو "مدير المخزن"
const isWarehouseManagerRole = (role) => {
    const roleName = getRoleName(role);
    return roleName === "مدير المخزن";
};

// التحقق مما إذا كان الدور هو "مدير القسم"
const isDepartmentManagerRole = (role) => {
    const roleName = getRoleName(role);
    return roleName === "مدير القسم";
};

// إنشاء قائمة الأقسام المتاحة
const filteredEditDepartments = computed(() => {
    if (!props.availableDepartments) return [];
    
    return props.availableDepartments.filter(dept => {
        // إذا كان القسم هو القسم الحالي للموظف، اسمح به
        if (dept === editForm.value.department) return true;
        // استبعد الأقسام التي لها مدير
        return !props.departmentsWithManager.includes(dept);
    });
});

// التحقق من صحة النموذج
const validateEditForm = () => {
    let isValid = true;
    const data = editForm.value;
    const roleList = getRoleList.value;

    editErrors.value.birth = !data.birth;
    if (editErrors.value.birth) isValid = false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/; 
    editErrors.value.phone = !phoneRegex.test(data.phone.trim());
    if (editErrors.value.phone) isValid = false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    editErrors.value.email = !emailRegex.test(data.email.trim());
    if (editErrors.value.email) isValid = false;

    // التحقق من حقل الدور الوظيفي
    editErrors.value.role = !data.role || !roleList.includes(data.role);
    if (editErrors.value.role) isValid = false;

    // التحقق من حقل القسم إذا كان الدور هو "مدير القسم"
    if (isDepartmentManagerRole(data.role)) {
        editErrors.value.department = !data.department;
        if (editErrors.value.department) isValid = false;
        
        // التحقق من أن القسم ليس لديه مدير بالفعل
        const currentEmployee = props.patient;
        const isSameDepartment = currentEmployee && currentEmployee.department === data.department;
        
        if (!isSameDepartment && props.departmentsWithManager.includes(data.department)) {
            editErrors.value.department = true;
            alert("❌ هذا القسم لديه مدير بالفعل!");
            isValid = false;
        }
    }

    // التحقق من وجود مدير مخزن إذا كان الدور هو "مدير المخزن"
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager) {
        const currentEmployee = props.patient;
        const currentRoleName = currentEmployee ? getRoleName(currentEmployee.role) : '';
        
        if (!currentEmployee || currentRoleName !== "مدير المخزن") {
            alert("❌ يوجد بالفعل مدير مخزن مفعل في النظام!");
            isValid = false;
        }
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isEditFormValid = computed(() => {
    const data = editForm.value;
    const roleList = getRoleList.value;
    
    if (!data.birth) return false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/; 
    if (!phoneRegex.test(data.phone.trim())) return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email.trim())) return false;

    // التحقق من حقل الدور الوظيفي
    if (!data.role || !roleList.includes(data.role)) return false;

    // التحقق من حقل القسم إذا كان الدور هو "مدير القسم"
    if (isDepartmentManagerRole(data.role)) {
        if (!data.department) return false;
        
        const currentEmployee = props.patient;
        const isSameDepartment = currentEmployee && currentEmployee.department === data.department;
        
        if (!isSameDepartment && props.departmentsWithManager.includes(data.department)) {
            return false;
        }
    }

    // التحقق من وجود مدير مخزن
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager) {
        const currentEmployee = props.patient;
        const currentRoleName = currentEmployee ? getRoleName(currentEmployee.role) : '';
        
        if (!currentEmployee || currentRoleName !== "مدير المخزن") {
            return false;
        }
    }

    return true;
});

// التحقق من وجود تغييرات
const isEditFormModified = computed(() => {
    const current = editForm.value;
    const original = originalEditForm.value;

    if (!original.fileNumber) return false;

    if (current.birth !== original.birth) return true;
    if (current.phone !== original.phone) return true;
    if (current.email !== original.email) return true;
    if (current.role !== original.role) return true;
    if (current.department !== original.department) return true;
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
    const updatedEmployee = {
        fileNumber: editForm.value.fileNumber,
        name: editForm.value.name,
        nationalId: editForm.value.nationalId,
        birth: editForm.value.birth.replace(/-/g, "/"),
        phone: editForm.value.phone,
        email: editForm.value.email,
        role: editForm.value.role,
        department: isDepartmentManagerRole(editForm.value.role) ? editForm.value.department : "",
        isActive: editForm.value.isActive,
    };
    
    emit('save', updatedEmployee);
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

// مراقبة تغيير الدور
watch(() => editForm.value.role, (newRole) => {
    if (!isDepartmentManagerRole(newRole)) {
        editForm.value.department = "";
    }
});

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.patient) {
        const formattedBirth = props.patient.birth ? props.patient.birth.replace(/\//g, "-") : '';

        // استخراج اسم الدور من البيانات
        const roleValue = props.patient.role ? getRoleName(props.patient.role) : "";

        const initialData = {
            fileNumber: props.patient.fileNumber,
            nationalId: props.patient.nationalId,
            name: props.patient.name,
            birth: formattedBirth,
            phone: props.patient.phone,
            email: props.patient.email || "",
            role: roleValue,
            department: props.patient.department || "",
            isActive: props.patient.isActive !== undefined ? props.patient.isActive : true,
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            birth: false,
            phone: false,
            email: false,
            role: false,
            department: false,
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
                                    v-model="editForm.name"
                                    class="h-9 text-right rounded-2xl w-full border-gray-300 bg-gray-200 text-gray-600 cursor-not-allowed focus:ring-0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-role" class="text-right font-medium text-[#2E5077] pt-2">الدور الوظيفي</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    id="edit-role"
                                    v-model="editForm.role"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.role, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.role }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="" disabled>اختر الدور الوظيفي</option>
                                    <option v-for="role in getRoleList" :key="role" :value="role">
                                        {{ role }}
                                    </option>
                                </select>
                                <Icon v-if="editErrors.role" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.role" class="text-xs text-red-500 mt-1">الرجاء اختيار الدور الوظيفي.</p>
                            </div>
                        </div>

                        <!-- حقل القسم يظهر فقط عندما يكون الدور "مدير قسم" -->
                        <div v-if="isDepartmentManagerRole(editForm.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-department" class="text-right font-medium text-[#2E5077] pt-2">اسم القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    id="edit-department"
                                    v-model="editForm.department"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.department, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.department }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="" disabled>اختر القسم</option>
                                    <option v-for="dept in filteredEditDepartments" :key="dept" :value="dept">
                                        {{ dept }}
                                    </option>
                                </select>
                                <Icon v-if="editErrors.department" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.department" class="text-xs text-red-500 mt-1">
                                    {{ editForm.department && props.departmentsWithManager.includes(editForm.department) && editForm.department !== originalEditForm.department
                                        ? 'هذا القسم لديه مدير بالفعل!' 
                                        : 'الرجاء اختيار القسم.' }}
                                </p>
                            </div>
                        </div>

                        <!-- تنبيه للأقسام التي لها مدير بالفعل -->
                        <div v-if="isDepartmentManagerRole(editForm.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2"></Label>
                            <div class="relative w-full sm:w-75">
                                <div v-if="props.departmentsWithManager && props.departmentsWithManager.length > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                    <Icon icon="tabler:info-circle" class="w-4 h-4 inline ml-1" />
                                    <span class="font-semibold">ملاحظة:</span> الأقسام التالية لها مدير بالفعل:
                                    <ul class="mt-1 list-disc list-inside rtl">
                                        <li v-for="dept in props.departmentsWithManager" :key="dept">
                                            {{ dept }}
                                        </li>
                                    </ul>
                                </div>
                                <div v-else class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">
                                    <Icon icon="tabler:check" class="w-4 h-4 inline ml-1" />
                                    جميع الأقسام متاحة لتولي منصب المدير.
                                </div>
                            </div>
                        </div>

                        <!-- تنبيه لمدير المخزن -->
                        <div v-if="isWarehouseManagerRole(editForm.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2"></Label>
                            <div class="relative w-full sm:w-75">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                    <Icon icon="tabler:info-circle" class="w-4 h-4 inline ml-1" />
                                    <span class="font-semibold">ملاحظة:</span> يمكن تعيين مدير مخزن واحد فقط في النظام.
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-isActive" class="text-right font-medium text-[#2E5077] pt-2">حالة الحساب</Label>
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

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="edit-birth" class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    id="edit-birth"
                                    type="date" 
                                    v-model="editForm.birth"
                                    :class="{ 'border-red-500 hover:border-red-500': editErrors.birth, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !editErrors.birth }"
                                    class="h-9 text-right w-full pr-3 appearance-none rounded-2xl bg-white"
                                />
                                <Icon v-if="editErrors.birth" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="editErrors.birth" class="text-xs text-red-500 mt-1">الرجاء تحديد تاريخ الميلاد.</p>
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
                    لقد قمت بإدخال بعض التعديلات للبيانات.
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في الحفظ؟
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