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
    hasWarehouseManager: Boolean,
    availableDepartments: Array,
    availableRoles: Array,
    departmentsWithManager: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    nationalId: "",
    name: "",
    birth: "",
    phone: "",
    email: "",
    role: "",
    department: "",
});

// أخطاء التحقق
const errors = ref({
    nationalId: false,
    name: false,
    birth: false,
    phone: false,
    email: false,
    role: false,
    department: false,
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// إعادة تعيين النموذج
const resetForm = () => {
    form.value = { 
        nationalId: "", 
        name: "", 
        birth: "", 
        phone: "", 
        email: "",
        role: "",
        department: ""
    };
    errors.value = { 
        nationalId: false, 
        name: false, 
        birth: false, 
        phone: false, 
        email: false,
        role: false,
        department: false
    };
};

// إنشاء قائمة الأقسام المتاحة
const filteredDepartments = computed(() => {
    if (!props.availableDepartments) return [];
    
    return props.availableDepartments.filter(dept => 
        !props.departmentsWithManager.includes(dept)
    );
});

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

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    const nationalIdRegex = /^\d{12}$/;
    errors.value.nationalId = !nationalIdRegex.test(data.nationalId);
    if (errors.value.nationalId) isValid = false;

    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]{3,}$/;
    errors.value.name = !nameRegex.test(data.name.trim());
    if (errors.value.name) isValid = false;

    errors.value.birth = !data.birth;
    if (errors.value.birth) isValid = false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/;
    errors.value.phone = !phoneRegex.test(data.phone.trim());
    if (errors.value.phone) isValid = false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    errors.value.email = !emailRegex.test(data.email.trim());
    if (errors.value.email) isValid = false;

    // التحقق من حقل الدور الوظيفي
    const roleList = getRoleList.value;
    errors.value.role = !data.role || !roleList.includes(data.role);
    if (errors.value.role) isValid = false;

    // التحقق من حقل القسم إذا كان الدور هو "مدير القسم"
    if (isDepartmentManagerRole(data.role)) {
        errors.value.department = !data.department;
        if (errors.value.department) isValid = false;
        
        // التحقق من أن القسم ليس لديه مدير بالفعل
        if (props.departmentsWithManager.includes(data.department)) {
            errors.value.department = true;
            isValid = false;
        }
    }

    // التحقق من وجود مدير مخزن إذا كان الدور هو "مدير المخزن"
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager) {
        alert("❌ يوجد بالفعل مدير مخزن مفعل في النظام!");
        isValid = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    const roleList = getRoleList.value;
    
    const nationalIdRegex = /^\d{12}$/;
    if (!nationalIdRegex.test(data.nationalId)) return false;

    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]{3,}$/;
    if (!nameRegex.test(data.name.trim())) return false;

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
        
        // التحقق من أن القسم ليس لديه مدير بالفعل
        if (props.departmentsWithManager.includes(data.department)) {
            return false;
        }
    }

    // التحقق من وجود مدير مخزن
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager) {
        return false;
    }

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
    const newEmployee = {
        name: form.value.name,
        nationalId: form.value.nationalId,
        birth: form.value.birth.replace(/-/g, "/"),
        phone: form.value.phone,
        email: form.value.email,
        role: form.value.role,
        department: isDepartmentManagerRole(form.value.role) ? form.value.department : "",
    };
    
    emit('save', newEmployee);
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

// مراقبة تغيير الدور لإعادة تعيين حقل القسم
watch(() => form.value.role, (newRole) => {
    if (!isDepartmentManagerRole(newRole)) {
        form.value.department = "";
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
                        icon="mingcute:user-add-line"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج تسجيل موظف
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
                        المعلومات الشخصية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="national-id" class="text-right font-medium text-[#2E5077] pt-2">الرقم الوطني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="national-id"
                                    v-model="form.nationalId"
                                    placeholder="أدخل الرقم الوطني"
                                    type="text"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.nationalId, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9] ': !errors.nationalId }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="errors.nationalId" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.nationalId" class="text-xs text-red-500 mt-1">الرجاء إدخال الرقم الوطني بشكل صحيح (12 رقم).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="name" class="text-right font-medium text-[#2E5077] pt-2">الإسم رباعي</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="name"
                                    v-model="form.name"
                                    placeholder="أدخل الإسم الرباعي "
                                    :class="{ 'border-red-500 hover:border-red-500': errors.name, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.name }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                    <Icon v-if="errors.name" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.name" class="text-xs text-red-500 mt-1">الرجاء إدخال الاسم الرباعي بشكل صحيح.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="role" class="text-right font-medium text-[#2E5077] pt-2">الدور الوظيفي</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    required
                                    id="role"
                                    v-model="form.role"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.role, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9] ': !errors.role }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="" disabled selected></option>
                                    <option v-for="role in getRoleList" :key="role" :value="role">
                                        {{ role }}
                                    </option>
                                </select>
                                <Icon v-if="errors.role" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.role" class="text-xs text-red-500 mt-1">الرجاء اختيار الدور الوظيفي.</p>
                            </div>
                        </div>

                        <!-- حقل القسم يظهر فقط عندما يكون الدور "مدير قسم" -->
                        <div v-if="isDepartmentManagerRole(form.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="department" class="text-right font-medium text-[#2E5077] pt-2">اسم القسم</Label>
                            <div class="relative w-full sm:w-75">
                                <select
                                    required
                                    id="department"
                                    v-model="form.department"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.department, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.department }"
                                    class="h-9 text-right w-full rounded-2xl bg-white px-3 border focus:outline-none"
                                >
                                    <option value="" disabled selected>اختر القسم</option>
                                    <option v-for="dept in filteredDepartments" :key="dept" :value="dept">
                                        {{ dept }}
                                    </option>
                                </select>
                                <Icon v-if="errors.department" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.department" class="text-xs text-red-500 mt-1">
                                    {{ form.department && props.departmentsWithManager.includes(form.department) 
                                        ? 'هذا القسم لديه مدير بالفعل!' 
                                        : 'الرجاء اختيار القسم.' }}
                                </p>
                            </div>
                        </div>

                        <!-- تنبيه للأقسام التي لها مدير بالفعل -->
                        <div v-if="isDepartmentManagerRole(form.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
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
                        <div v-if="isWarehouseManagerRole(form.role)" class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label class="text-right font-medium text-[#2E5077] pt-2"></Label>
                            <div class="relative w-full sm:w-75">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                    <Icon icon="tabler:info-circle" class="w-4 h-4 inline ml-1" />
                                    <span class="font-semibold">ملاحظة:</span> يمكن تعيين مدير مخزن واحد فقط في النظام.
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="birth" class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="birth"
                                    type="date"
                                    v-model="form.birth"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.birth, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.birth }"
                                    class="h-9 text-right w-full pr-3 pl-40 appearance-none rounded-2xl bg-white"
                                />
                                    <Icon v-if="errors.birth" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.birth" class="text-xs text-red-500 mt-1">الرجاء تحديد تاريخ الميلاد.</p>
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
                            <Label for="phone" class="text-right font-medium text-[#2E5077] pt-2">رقم الهاتف</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="phone"
                                    v-model="form.phone"
                                    placeholder="أدخل رقم الهاتف"
                                    type="text"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.phone, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.phone }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="errors.phone" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.phone" class="text-xs text-red-500 mt-1">تأكد من إدخال رقم هاتف صحيح (مثال: 091xxxxxxx).</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[80px_1fr] items-start gap-4">
                            <Label for="email" class="text-right font-medium text-[#2E5077] pt-2">البريدالإلكتروني</Label>
                            <div class="relative w-full sm:w-75">
                                <Input
                                    required
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    placeholder="أدخل البريد الإلكتروني"
                                    :class="{ 'border-red-500 hover:border-red-500': errors.email, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.email }"
                                    class="h-9 text-right w-full rounded-2xl bg-white"
                                />
                                <Icon v-if="errors.email" icon="tabler:alert-triangle-filled" class="w-5 h-5 text-red-500 absolute left-2 top-2" />
                                <p v-if="errors.email" class="text-xs text-red-500 mt-1">تأكد من إدخال البريد الإلكتروني صحيح.</p>
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
                    لقد قمت بتسجيل بيانات لحساب جديد.
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في إنشاء هذا الحساب؟
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