<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import Input from "@/components/ui/input/Input.vue";

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
    
    // نعرض كل الأقسام دائماً، لكن الأقسام التي لديها مدير ستكون معطلة في القائمة المنسدلة
    return props.availableDepartments;
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

    // لا يوجد حقل قسم في نموذج الإضافة
    errors.value.department = false;

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

    // لا يوجد حقل قسم في نموذج الإضافة

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
        department: null, // لا يوجد قسم في نموذج الإضافة
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

// لا يوجد حقل قسم في نموذج الإضافة
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
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
            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                
                <!-- المعلومات الشخصية والوظيفية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-id-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المعلومات الشخصية والوظيفية
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- الرقم الوطني -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الرقم الوطني</label>
                            <div class="relative">
                                <Input
                                    required
                                    v-model="form.nationalId"
                                    placeholder="أدخل الرقم الوطني"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.nationalId, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.nationalId }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.nationalId" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.nationalId" class="text-sm text-red-500 font-medium">الرجاء إدخال الرقم الوطني بشكل صحيح (12 رقم).</p>
                        </div>

                        <!-- الاسم الرباعي -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الإسم رباعي</label>
                            <div class="relative">
                                <Input
                                    required
                                    v-model="form.name"
                                    placeholder="أدخل الإسم الرباعي"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.name }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.name" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.name" class="text-sm text-red-500 font-medium">الرجاء إدخال الاسم الرباعي بشكل صحيح.</p>
                        </div>

                        <!-- الدور الوظيفي -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الدور الوظيفي</label>
                            <div class="relative">
                                <select
                                    required
                                    v-model="form.role"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.role, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.role }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="" disabled selected>اختر الدور الوظيفي</option>
                                    <option v-for="role in getRoleList" :key="role" :value="role">
                                        {{ role }}
                                    </option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5 pointer-events-none" />
                            </div>
                            <p v-if="errors.role" class="text-sm text-red-500 font-medium">الرجاء اختيار الدور الوظيفي.</p>
                        </div>

                        <!-- تاريخ الميلاد -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">تاريخ الميلاد</label>
                            <div class="relative">
                                <Input
                                    required
                                    type="date"
                                    v-model="form.birth"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.birth, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.birth }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.birth" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.birth" class="text-sm text-red-500 font-medium">الرجاء تحديد تاريخ الميلاد.</p>
                        </div>


                        <div v-if="isWarehouseManagerRole(form.role)" class="md:col-span-2">
                            <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 flex gap-3">
                                <Icon icon="solar:shield-warning-bold" class="w-6 h-6 text-yellow-600 flex-shrink-0" />
                                <p class="text-sm text-yellow-700 font-medium">
                                    <span class="font-bold">ملاحظة:</span> يمكن تعيين مدير مخزن واحد فقط في النظام.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات الإتصال -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:phone-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الإتصال
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">رقم الهاتف</label>
                            <div class="relative">
                                <Input
                                    required
                                    v-model="form.phone"
                                    placeholder="أدخل رقم الهاتف"
                                    type="text"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.phone, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.phone }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.phone" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.phone" class="text-sm text-red-500 font-medium">تأكد من إدخال رقم هاتف صحيح.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">البريد الإلكتروني</label>
                            <div class="relative">
                                <Input
                                    required
                                    type="email"
                                    v-model="form.email"
                                    placeholder="أدخل البريد الإلكتروني"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.email, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.email }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.email" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.email" class="text-sm text-red-500 font-medium">تأكد من إدخال البريد الإلكتروني صحيح.</p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="submitForm"
                    :disabled="!isFormValid"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                    حفظ الموظف
                </button>
            </div>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div v-if="isConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:shield-warning-bold-duotone" class="w-8 h-8 text-yellow-500" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد تسجيل الموظف</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في إنشاء حساب جديد للموظف <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
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
                    @click="confirmRegistration" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20"
                >
                    تأكيد التسجيل
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
