<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import Input from "@/components/ui/input/Input.vue";
import axios from "axios";

const props = defineProps({
    isOpen: Boolean,
    patient: Object, // Keeping the prop name as 'patient' to match employeesList.vue
    hasWarehouseManager: Boolean,
    availableDepartments: Array,
    availableRoles: Array,
    departmentsWithManager: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    id: "",
    nationalId: "",
    name: "",
    birth: "",
    phone: "",
    email: "",
    role: "",
    department: "",
    isActive: true,
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

// رسائل الأخطاء
const errorMessages = ref({
    nationalId: "",
    phone: "",
    email: "",
    name: "",
});

// حالة التحقق من التكرار
const duplicateErrors = ref({
    phone: false,
    email: false,
    nationalId: false,
});

let debounceTimeout = null;

// Helper to get headers with token
const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    return {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };
};

// التحقق من التكرار
const checkUniqueness = async () => {
    // Clear previous timeout
    if (debounceTimeout) clearTimeout(debounceTimeout);
    
    // Clear duplicate errors initially
    duplicateErrors.value = {
        phone: false,
        email: false,
        nationalId: false,
    };

    // Validation prerequisites for check
    const phone = form.value.phone?.trim();
    const email = form.value.email?.trim();
    const nationalId = form.value.nationalId?.trim();
    const employeeId = form.value.id;
    
    // التحقق من صحة الصيغة أولاً
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const nationalIdRegex = /^[12]\d{11}$/;
    
    const phoneValid = phone && phoneRegex.test(phone);
    const emailValid = email && emailRegex.test(email);
    const nationalIdValid = nationalId && nationalIdRegex.test(nationalId);
    
    // إذا لم تكن الصيغة صحيحة، لا نتحقق من التكرار
    if (!phoneValid && !emailValid && !nationalIdValid) {
        return;
    }

    debounceTimeout = setTimeout(async () => {
        try {
            const response = await axios.post('/api/admin-hospital/staff/check-unique', {
                phone: phoneValid ? phone : null,
                email: emailValid ? email : null,
                national_id: nationalIdValid ? nationalId : null,
                exclude_id: employeeId || null
            }, getAuthHeaders());

            if (response.data.success && response.data.data) {
                const data = response.data.data;
                if (data.exists) {
                    if (data.fields.phone && phoneValid) {
                        duplicateErrors.value.phone = true;
                        errorMessages.value.phone = data.messages.phone || 'رقم الهاتف موجود بالفعل في النظام';
                    }
                    if (data.fields.email && emailValid) {
                        duplicateErrors.value.email = true;
                        errorMessages.value.email = data.messages.email || 'البريد الإلكتروني موجود بالفعل في النظام';
                    }
                    if (data.fields.national_id && nationalIdValid) {
                        duplicateErrors.value.nationalId = true;
                        errorMessages.value.nationalId = data.messages.national_id || 'الرقم الوطني موجود بالفعل في النظام';
                    }
                } else {
                    // إذا لم يكن هناك تكرار، نمسح رسائل التكرار فقط (نحتفظ برسائل الصيغة)
                    if (phoneValid && !data.fields?.phone) {
                        if (errorMessages.value.phone && errorMessages.value.phone.includes('موجود بالفعل')) {
                            errorMessages.value.phone = "";
                        }
                    }
                    if (emailValid && !data.fields?.email) {
                        if (errorMessages.value.email && errorMessages.value.email.includes('موجود بالفعل')) {
                            errorMessages.value.email = "";
                        }
                    }
                    if (nationalIdValid && !data.fields?.national_id) {
                        if (errorMessages.value.nationalId && errorMessages.value.nationalId.includes('موجود بالفعل')) {
                            errorMessages.value.nationalId = "";
                        }
                    }
                }
            }
        } catch (error) {
            console.error("Check unique error", error);
        }
    }, 500); // 500ms debounce
};

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// التحقق من الصحة في الوقت الفعلي
const validateField = (fieldName) => {
    const data = form.value;
    
    if (fieldName === 'name') {
        const nameTrimmed = data.name?.trim() || '';
        // التحقق من أن الاسم يحتوي على 4 أسماء فما فوق (مفصولة بمسافات)
        const nameParts = nameTrimmed.split(/\s+/).filter(part => part.length > 0);
        const hasFourNamesOrMore = nameParts.length >= 4;
        
        // التحقق من عدم وجود أرقام أو رموز خاصة (فقط أحرف عربية وإنجليزية ومسافات)
        const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
        const hasNoNumbersOrSpecialChars = nameRegex.test(nameTrimmed);
        
        errors.value.name = !hasFourNamesOrMore || !hasNoNumbersOrSpecialChars;
        
        if (!hasFourNamesOrMore && nameTrimmed) {
            errorMessages.value.name = "يجب إدخال 4 أسماء فما فوق (مفصولة بمسافات)";
        } else if (!hasNoNumbersOrSpecialChars && nameTrimmed) {
            errorMessages.value.name = "الاسم لا يجب أن يحتوي على أرقام أو رموز خاصة";
        } else if (!nameTrimmed) {
            errorMessages.value.name = "";
        } else {
            errorMessages.value.name = "";
        }
    }
    
    if (fieldName === 'phone') {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        const phoneValid = phoneRegex.test(data.phone?.trim() || '');
        errors.value.phone = !phoneValid;
        if (!phoneValid && data.phone) {
            errorMessages.value.phone = "رقم الهاتف يجب أن يبدأ بـ 021 أو 092 أو 091 أو 093 أو 094 ثم يتبعه 7 أرقام";
        } else if (!data.phone) {
            errorMessages.value.phone = "";
        }
    }
    
    if (fieldName === 'nationalId') {
        const nationalIdRegex = /^[12]\d{11}$/;
        const nationalIdValid = nationalIdRegex.test(data.nationalId?.trim() || '');
        errors.value.nationalId = !nationalIdValid;
        if (!nationalIdValid && data.nationalId) {
            errorMessages.value.nationalId = "الرقم الوطني يجب أن يبدأ بـ 1 أو 2 ثم يتبعه 11 رقم (إجمالي 12 رقم)";
        } else if (!data.nationalId) {
            errorMessages.value.nationalId = "";
        }
    }
    
    if (fieldName === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const emailValid = emailRegex.test(data.email?.trim() || '');
        errors.value.email = !emailValid;
        if (!emailValid && data.email) {
            errorMessages.value.email = "البريد الإلكتروني غير صحيح";
        } else if (!data.email) {
            errorMessages.value.email = "";
        }
    }
};

// مراقبة التغييرات في الحقول للتحقق من التكرار والصحة
watch([() => form.value.phone, () => form.value.email, () => form.value.nationalId, () => form.value.name], ([phone, email, nationalId, name], [oldPhone, oldEmail, oldNationalId, oldName]) => {
    if (props.isOpen) {
        if (phone !== oldPhone) {
            validateField('phone');
            checkUniqueness();
        }
        if (email !== oldEmail) {
            validateField('email');
            checkUniqueness();
        }
        if (nationalId !== oldNationalId) {
            validateField('nationalId');
            checkUniqueness();
        }
        if (name !== oldName) {
            validateField('name');
        }
    }
});

// تهيئة النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.patient) {
        const emp = props.patient;
        form.value = {
            id: emp.id || emp.fileNumber || "",
            nationalId: emp.nationalId || emp.nationalIdDisplay || "",
            name: emp.name || emp.nameDisplay || "",
            birth: emp.birth ? (emp.birth.replace ? emp.birth.replace(/\//g, "-") : emp.birth) : (emp.birthDisplay ? emp.birthDisplay.replace(/\//g, "-") : ""),
            phone: emp.phone || "",
            email: emp.email || "",
            role: emp.role || "",
            department: emp.department || "",
            isActive: emp.isActive !== undefined ? emp.isActive : true,
        };
        // Reset errors when opening modal
        errorMessages.value = {
            nationalId: "",
            phone: "",
            email: "",
            name: "",
        };
        duplicateErrors.value = {
            phone: false,
            email: false,
            nationalId: false,
        };
    } else {
        // Clear debounce timeout when modal closes
        if (debounceTimeout) {
            clearTimeout(debounceTimeout);
            debounceTimeout = null;
        }
    }
}, { immediate: true });

// إنشاء قائمة الأقسام المتاحة
const filteredDepartments = computed(() => {
    if (!props.availableDepartments) return [];
    return props.availableDepartments;
});

// الحصول على قائمة الأدوار بشكل صحيح
const getRoleList = computed(() => {
    if (!props.availableRoles) return [];
    if (props.availableRoles.length > 0 && typeof props.availableRoles[0] === 'object') {
        return props.availableRoles.map(role => role.name);
    }
    return props.availableRoles;
});

// التحقق من الأدوار
const isWarehouseManagerRole = (role) => role === "مدير المخزن";

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    // التحقق من الرقم الوطني: يبدأ بـ 1 أو 2 ثم سنة الميلاد (4 أرقام) ثم باقي الأرقام (إجمالي 12 رقم)
    const nationalIdRegex = /^[12]\d{11}$/;
    const nationalIdValid = nationalIdRegex.test(data.nationalId);
    errors.value.nationalId = !nationalIdValid;
    if (!nationalIdValid) {
        errorMessages.value.nationalId = "الرقم الوطني يجب أن يبدأ بـ 1 أو 2 ثم يتبعه 11 رقم (إجمالي 12 رقم)";
        isValid = false;
    } else if (duplicateErrors.value.nationalId) {
        errors.value.nationalId = true;
        isValid = false;
    } else {
        errorMessages.value.nationalId = "";
    }

    // التحقق من الاسم الرباعي: يجب أن يكون 4 أسماء فما فوق بدون أرقام أو رموز خاصة
    const nameTrimmed = data.name?.trim() || '';
    const nameParts = nameTrimmed.split(/\s+/).filter(part => part.length > 0);
    const hasFourNamesOrMore = nameParts.length >= 4;
    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
    const hasNoNumbersOrSpecialChars = nameRegex.test(nameTrimmed);
    
    errors.value.name = !hasFourNamesOrMore || !hasNoNumbersOrSpecialChars;
    if (!hasFourNamesOrMore && nameTrimmed) {
        errorMessages.value.name = "يجب إدخال 4 أسماء فما فوق (مفصولة بمسافات)";
        isValid = false;
    } else if (!hasNoNumbersOrSpecialChars && nameTrimmed) {
        errorMessages.value.name = "الاسم لا يجب أن يحتوي على أرقام أو رموز خاصة";
        isValid = false;
    } else if (!nameTrimmed) {
        errorMessages.value.name = "الاسم الرباعي مطلوب";
        isValid = false;
    } else {
        errorMessages.value.name = "";
    }

    errors.value.birth = !data.birth;
    if (errors.value.birth) isValid = false;

    // التحقق من رقم الهاتف: يبدأ بـ 021 أو 092 أو 091 أو 093 أو 094 ثم 7 أرقام
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    const phoneValid = phoneRegex.test(data.phone.trim());
    errors.value.phone = !phoneValid;
    if (!phoneValid) {
        errorMessages.value.phone = "رقم الهاتف يجب أن يبدأ بـ 021 أو 092 أو 091 أو 093 أو 094 ثم يتبعه 7 أرقام";
        isValid = false;
    } else if (duplicateErrors.value.phone) {
        errors.value.phone = true;
        isValid = false;
    } else {
        errorMessages.value.phone = "";
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const emailValid = emailRegex.test(data.email.trim());
    errors.value.email = !emailValid;
    if (!emailValid) {
        errorMessages.value.email = "البريد الإلكتروني غير صحيح";
        isValid = false;
    } else if (duplicateErrors.value.email) {
        errors.value.email = true;
        isValid = false;
    } else {
        errorMessages.value.email = "";
    }

    const roleList = getRoleList.value;
    errors.value.role = !data.role || !roleList.includes(data.role);
    if (errors.value.role) isValid = false;

    // التحقق من وجود مدير مخزن آخر
    const currentRole = props.patient?.role || "";
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager && currentRole !== "مدير المخزن") {
        isValid = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    const roleList = getRoleList.value;
    
    // التحقق من الرقم الوطني: يبدأ بـ 1 أو 2 ثم 11 رقم (إجمالي 12 رقم)
    const nationalIdRegex = /^[12]\d{11}$/;
    if (!nationalIdRegex.test(data.nationalId)) return false;
    if (duplicateErrors.value.nationalId) return false;

    // التحقق من الاسم الرباعي: يجب أن يكون 4 أسماء فما فوق بدون أرقام أو رموز خاصة
    const nameTrimmed = data.name?.trim() || '';
    const nameParts = nameTrimmed.split(/\s+/).filter(part => part.length > 0);
    const hasFourNamesOrMore = nameParts.length >= 4;
    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
    const hasNoNumbersOrSpecialChars = nameRegex.test(nameTrimmed);
    
    if (!hasFourNamesOrMore || !hasNoNumbersOrSpecialChars || !nameTrimmed) return false;

    if (!data.birth) return false;

    // التحقق من رقم الهاتف: يبدأ بـ 021 أو 092 أو 091 أو 093 أو 094 ثم 7 أرقام
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    if (!phoneRegex.test(data.phone.trim())) return false;
    if (duplicateErrors.value.phone) return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email.trim())) return false;
    if (duplicateErrors.value.email) return false;

    if (!data.role || !roleList.includes(data.role)) return false;

    const currentRole = props.patient?.role || "";
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager && currentRole !== "مدير المخزن") {
        return false;
    }

    // التحقق من وجود تغييرات
    const emp = props.patient || {};
    const originalData = {
        id: emp.id || emp.fileNumber || "",
        nationalId: emp.nationalId || emp.nationalIdDisplay || "",
        name: emp.name || emp.nameDisplay || "",
        birth: emp.birth ? (emp.birth.replace ? emp.birth.replace(/\//g, "-") : emp.birth) : (emp.birthDisplay ? emp.birthDisplay.replace(/\//g, "-") : ""),
        phone: emp.phone || "",
        email: emp.email || "",
        role: emp.role || "",
        isActive: emp.isActive !== undefined ? emp.isActive : true,
    };
    const currentData = {
        id: form.value.id,
        nationalId: form.value.nationalId,
        name: form.value.name,
        birth: form.value.birth,
        phone: form.value.phone,
        email: form.value.email,
        role: form.value.role,
        isActive: form.value.isActive,
    };
    const hasChanges = JSON.stringify(currentData) !== JSON.stringify(originalData);

    return hasChanges;
});

const submitForm = () => {
    if (validateForm()) {
        showConfirmationModal();
    }
};

const showConfirmationModal = () => {
    isConfirmationModalOpen.value = true;
};

const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
};

const confirmUpdate = () => {
    const updatedEmployee = {
        ...form.value,
        birth: form.value.birth.replace(/-/g, "/"),
        department: "",
    };
    
    emit('save', updatedEmployee);
    closeConfirmationModal();
    emit('close');
};

const editDateInput = ref(null);
const openEditDatePicker = () => {
    if (editDateInput.value) {
        editDateInput.value.showPicker();
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100" dir="rtl">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات الموظف
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8 overflow-y-auto max-h-[70vh]">
                <!-- Readonly Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:file-text-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الملف
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="form.id" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-not-allowed focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 group">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الرقم الوطني
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                :value="form.nationalId" 
                                readonly 
                                class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 font-medium cursor-not-allowed focus:outline-none"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <Icon icon="solar:lock-bold" class="w-4 h-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Editable Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الإسم رباعي
                        </label>
                        <Input
                            v-model="form.name"
                            placeholder="أدخل الإسم الرباعي "
                            @input="validateField('name')"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.name }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errorMessages.name || "يجب إدخال 4 أسماء فما فوق بدون أرقام أو رموز خاصة" }}
                        </p>
                    </div>

                    <!-- Role -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-id-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الدور الوظيفي
                        </label>
                        <div class="relative">
                            <select
                                v-model="form.role"
                                :class="[
                                    'w-full h-10 px-3 pr-10 rounded-2xl bg-white border appearance-none focus:outline-none transition-colors duration-200',
                                    errors.role 
                                        ? 'border-red-500 focus:border-red-500' 
                                        : 'border-gray-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9]/20'
                                ]"
                            >
                                <option value="" disabled>اختر الدور الوظيفي</option>
                                <option v-for="role in getRoleList" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute right-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="errors.role" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء اختيار الدور الوظيفي
                        </p>
                    </div>

                    <!-- Birth Date -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            تاريخ الميلاد
                        </label>
                        
                        <div class="relative w-full">
                            <input
                                ref="editDateInput"
                                type="date" 
                                v-model="form.birth"
                                :class="[
                                    'h-9 text-right w-full pl-3 pr-10 appearance-none rounded-2xl bg-white cursor-pointer',
                                    'border focus:outline-none transition-colors duration-200',
                                    errors.birth 
                                        ? 'border-red-500 hover:border-red-500 focus:border-red-500' 
                                        : 'border-gray-200 hover:border-[#4DA1A9] focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9]/20'
                                ]"
                            />
                            
                            <div 
                                class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer" 
                                @click="openEditDatePicker"
                            >
                                <Icon 
                                    icon="solar:calendar-linear" 
                                    class="w-5 h-5 transition-colors duration-200"
                                    :class="errors.birth ? 'text-red-500' : 'text-[#79D7BE]'"
                                />
                            </div>
                        </div>

                        <p v-if="errors.birth" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            تاريخ الميلاد مطلوب
                        </p>
                    </div>

                    <!-- Phone -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <Input
                            v-model="form.phone"
                            placeholder="021XXXXXXX أو 092XXXXXXX"
                            @input="validateField('phone')"
                            :class="[
                                'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right',
                                (errors.phone || duplicateErrors.phone) ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                            ]"
                        />
                        <p v-if="errors.phone || duplicateErrors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errorMessages.phone || "رقم الهاتف يجب أن يبدأ بـ 021 أو 092 أو 091 أو 093 أو 094 ثم يتبعه 7 أرقام" }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني
                        </label>
                        <Input
                            type="email"
                            v-model="form.email"
                            placeholder="example@domain.com"
                            @input="validateField('email')"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.email || duplicateErrors.email }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.email || duplicateErrors.email" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errorMessages.email || "البريد الإلكتروني غير صحيح" }}
                        </p>
                    </div>

                    <!-- Account Status -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2 mb-2">
                            <Icon icon="solar:shield-user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة الحساب
                        </label>
                        <div class="flex gap-4">
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="form.isActive" :value="true" class="peer sr-only" />
                                <div class="px-6 py-2 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 transition-all flex items-center gap-2">
                                    <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                                    <span class="font-bold">مفعل</span>
                                </div>
                            </label>
                            <label class="cursor-pointer relative">
                                <input type="radio" v-model="form.isActive" :value="false" class="peer sr-only" />
                                <div class="px-6 py-2 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 transition-all flex items-center gap-2">
                                    <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                    <span class="font-bold">معطل</span>
                                </div>
                            </label>
                        </div>
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
                    :disabled="!isFormValid"
                    :class="[
                        'px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200',
                        isFormValid 
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
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في حفظ التعديلات للموظف <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
                    <br>
                    <span class="text-sm text-[#4DA1A9] mt-2 block">
                        سيتم تحديث بيانات الموظف في النظام
                    </span>
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
