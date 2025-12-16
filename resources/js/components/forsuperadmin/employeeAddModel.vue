<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean,
    hasWarehouseManager: Boolean,
    availableDepartments: Array,
    availableRoles: Array,
    departmentsWithManager: Array,
    availableHospitals: Array // إضافة المستشفيات المتاحة
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
    hospital: "", // إضافة حقل المستشفى
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
    hospital: false, // إضافة خطأ للمستشفى
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
        department: "",
        hospital: "" // إعادة تعيين المستشفى
    };
    errors.value = { 
        nationalId: false, 
        name: false, 
        birth: false, 
        phone: false, 
        email: false, 
        role: false, 
        department: false,
        hospital: false // إعادة تعيين خطأ المستشفى
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
    
    if (props.availableRoles.length > 0 && typeof props.availableRoles[0] === 'object') {
        return props.availableRoles.map(role => role.name);
    }
    
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

    // التحقق من حقل المستشفى
    errors.value.hospital = !data.hospital || !props.availableHospitals?.includes(data.hospital);
    if (errors.value.hospital) isValid = false;

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
        
        if (props.departmentsWithManager.includes(data.department)) {
            return false;
        }
    }

    // التحقق من وجود مدير مخزن
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager) {
        return false;
    }

    // التحقق من حقل المستشفى
    if (!data.hospital || !props.availableHospitals?.includes(data.hospital)) {
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
        hospital: form.value.hospital, // إضافة المستشفى
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

const maxDate = computed(() => {
    const today = new Date();
    today.setDate(today.getDate() - 1);
    return today.toISOString().split('T')[0];
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:user-plus-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تسجيل موظف جديد
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- National ID -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الرقم الوطني
                        </label>
                        <Input
                            id="national-id"
                            v-model="form.nationalId"
                            placeholder="أدخل الرقم الوطني"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.nationalId }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.nationalId" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال الرقم الوطني بشكل صحيح (12 رقم)
                        </p>
                    </div>

                    <!-- Name -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الإسم رباعي
                        </label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="أدخل الإسم الرباعي"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.name }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال الاسم الرباعي بشكل صحيح
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
                                id="role"
                                v-model="form.role"
                                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.role }"
                                class="w-full h-10 px-3 rounded-md bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:outline-none appearance-none"
                            >
                                <option value="" disabled selected>اختر الدور الوظيفي</option>
                                <option v-for="role in getRoleList" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
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
                            <Input
                                id="birth"
                                type="date" 
                                :max="maxDate" 
                                v-model="form.birth"
                                :class="{ 'border-red-500 hover:border-red-500': errors.birth, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.birth }"
                                class="h-9 text-right w-full pr-3 appearance-none rounded-2xl bg-white"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
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

                    <!-- Hospital -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المستشفى
                        </label>
                        <div class="relative">
                            <select
                                id="hospital"
                                v-model="form.hospital"
                                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.hospital }"
                                class="w-full h-10 px-3 rounded-md bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:outline-none appearance-none"
                            >
                                <option value="" disabled selected>اختر المستشفى</option>
                                <option v-for="hospital in availableHospitals" :key="hospital" :value="hospital">
                                    {{ hospital }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="errors.hospital" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء اختيار المستشفى
                        </p>
                    </div>

                    <!-- Department (Conditional) -->
                    <div v-if="isDepartmentManagerRole(form.role)" class="space-y-2 md:col-span-2 animate-in fade-in slide-in-from-top-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:buildings-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم القسم
                        </label>
                        <div class="relative">
                            <select
                                id="department"
                                v-model="form.department"
                                :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.department }"
                                class="w-full h-10 px-3 rounded-md bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:outline-none appearance-none"
                            >
                                <option value="" disabled selected>اختر القسم</option>
                                <option v-for="dept in filteredDepartments" :key="dept" :value="dept">
                                    {{ dept }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="errors.department" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ form.department && props.departmentsWithManager.includes(form.department) 
                                ? 'هذا القسم لديه مدير بالفعل!' 
                                : 'الرجاء اختيار القسم' }}
                        </p>
                    </div>

                    <!-- Messages -->
                    <div v-if="isDepartmentManagerRole(form.role)" class="md:col-span-2">
                        <div v-if="props.departmentsWithManager && props.departmentsWithManager.length > 0" class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
                            <Icon icon="solar:info-circle-bold" class="w-6 h-6 text-blue-600 flex-shrink-0" />
                            <div class="text-sm text-blue-700">
                                <span class="font-bold block mb-1">ملاحظة:</span>
                                الأقسام التالية لها مدير بالفعل:
                                <ul class="mt-1 list-disc list-inside">
                                    <li v-for="dept in props.departmentsWithManager" :key="dept">{{ dept }}</li>
                                </ul>
                            </div>
                        </div>
                        <div v-else class="bg-green-50 border border-green-100 rounded-xl p-4 flex gap-3">
                            <Icon icon="solar:check-circle-bold" class="w-6 h-6 text-green-600 flex-shrink-0" />
                            <p class="text-sm text-green-700 font-medium">جميع الأقسام متاحة لتولي منصب المدير.</p>
                        </div>
                    </div>

                    <div v-if="isWarehouseManagerRole(form.role)" class="md:col-span-2">
                        <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 flex gap-3">
                            <Icon icon="solar:shield-warning-bold" class="w-6 h-6 text-yellow-600 flex-shrink-0" />
                            <p class="text-sm text-yellow-700 font-medium">
                                <span class="font-bold">ملاحظة:</span> يمكن تعيين مدير مخزن واحد فقط في النظام.
                            </p>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="09XXXXXXXX"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.phone }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            رقم الهاتف غير صحيح
                        </p>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني
                        </label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            placeholder="example@domain.com"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.email }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.email" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            البريد الإلكتروني غير صحيح
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100">
                <button 
                    @click="closeModal" 
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
                    حفظ الموظف
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
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد تسجيل الموظف</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من صحة البيانات المدخلة؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">سيتم إنشاء حساب جديد للموظف</span>
                    <br>
                    <span class="text-sm font-medium text-gray-600 mt-2 block">
                        المستشفى: <span class="text-[#2E5077] font-bold">{{ form.hospital }}</span>
                    </span>
                </p>
            </div>
            <div class="flex justify-center bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeConfirmationModal" 
                    class=" px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    مراجعة
                </button>
                <button 
                    @click="confirmRegistration" 
                    class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
                >
                    تأكيد وإنشاء
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