<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: Boolean,
    employee: Object,
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

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// تهيئة النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.employee) {
        form.value = {
            id: props.employee.id,
            nationalId: props.employee.nationalId,
            name: props.employee.name,
            birth: props.employee.birth ? props.employee.birth.replace(/\//g, "-") : "",
            phone: props.employee.phone,
            email: props.employee.email,
            role: props.employee.role,
            department: props.employee.department || "",
            isActive: props.employee.isActive,
        };
    }
}, { immediate: true });

// إنشاء قائمة الأقسام المتاحة
const filteredDepartments = computed(() => {
    if (!props.availableDepartments) return [];
    
    // الأقسام المتاحة هي الأقسام التي ليس لها مدير + القسم الحالي للموظف إذا كان هو مديره
    return props.availableDepartments.filter(dept => 
        !props.departmentsWithManager.includes(dept) || dept === props.employee.department
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

// التحقق من الأدوار
const isWarehouseManagerRole = (role) => role === "مدير المخزن";
const isDepartmentManagerRole = (role) => role === "مدير القسم";

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

    const roleList = getRoleList.value;
    errors.value.role = !data.role || !roleList.includes(data.role);
    if (errors.value.role) isValid = false;

    if (isDepartmentManagerRole(data.role)) {
        errors.value.department = !data.department;
        if (errors.value.department) isValid = false;
        
        // التحقق من أن القسم ليس لديه مدير بالفعل (باستثناء القسم الحالي للموظف)
        if (props.departmentsWithManager.includes(data.department) && data.department !== props.employee.department) {
            errors.value.department = true;
            isValid = false;
        }
    }

    // التحقق من وجود مدير مخزن آخر
    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager && props.employee.role !== "مدير المخزن") {
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

    if (!data.role || !roleList.includes(data.role)) return false;

    if (isDepartmentManagerRole(data.role)) {
        if (!data.department) return false;
        if (props.departmentsWithManager.includes(data.department) && data.department !== props.employee.department) {
            return false;
        }
    }

    if (isWarehouseManagerRole(data.role) && props.hasWarehouseManager && props.employee.role !== "مدير المخزن") {
        return false;
    }

    // التحقق من وجود تغييرات
    const hasChanges = JSON.stringify(form.value) !== JSON.stringify({
        id: props.employee.id,
        nationalId: props.employee.nationalId,
        name: props.employee.name,
        birth: props.employee.birth ? props.employee.birth.replace(/\//g, "-") : "",
        phone: props.employee.phone,
        email: props.employee.email,
        role: props.employee.role,
        department: props.employee.department || "",
        isActive: props.employee.isActive,
    });

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
        department: isDepartmentManagerRole(form.value.role) ? form.value.department : "",
    };
    
    emit('save', updatedEmployee);
    closeConfirmationModal();
    emit('close');
};

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
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
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
                    تعديل بيانات الموظف
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                <!-- المعلومات الشخصية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:user-id-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المعلومات الشخصية والوظيفية
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- الرقم الوطني -->
                        <div class="space-y-2">
                            <Label for="national-id" class="text-gray-700 font-bold">الرقم الوطني</Label>
                            <div class="relative">
                                <Input
                                    required
                                    id="national-id"
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
                            <Label for="name" class="text-gray-700 font-bold">الإسم رباعي</Label>
                            <div class="relative">
                                <Input
                                    required
                                    id="name"
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
                            <Label for="role" class="text-gray-700 font-bold">الدور الوظيفي</Label>
                            <div class="relative">
                                <select
                                    required
                                    id="role"
                                    v-model="form.role"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.role, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.role }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="" disabled>اختر الدور الوظيفي</option>
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
                            <Label for="birth" class="text-gray-700 font-bold">تاريخ الميلاد</Label>
                            <div class="relative">
                                <Input
                                    required
                                    id="birth"
                                    type="date"
                                    v-model="form.birth"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.birth, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.birth }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all pr-4"
                                />
                                <Icon v-if="errors.birth" icon="solar:danger-circle-bold" class="w-5 h-5 text-red-500 absolute left-3 top-3.5" />
                            </div>
                            <p v-if="errors.birth" class="text-sm text-red-500 font-medium">الرجاء تحديد تاريخ الميلاد.</p>
                        </div>

                        <!-- حقل القسم (مشروط) -->
                        <div v-if="isDepartmentManagerRole(form.role)" class="space-y-2 sm:col-span-2 animate-in fade-in slide-in-from-top-2">
                            <Label for="department" class="text-gray-700 font-bold">اسم القسم</Label>
                            <div class="relative">
                                <select
                                    required
                                    id="department"
                                    v-model="form.department"
                                    :class="{ 'border-red-500 focus:ring-red-500/20': errors.department, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.department }"
                                    class="h-12 text-right w-full rounded-xl bg-gray-50 border-2 focus:ring-4 transition-all px-4 appearance-none focus:outline-none"
                                >
                                    <option value="" disabled>اختر القسم</option>
                                    <option v-for="dept in filteredDepartments" :key="dept" :value="dept">
                                        {{ dept }}
                                    </option>
                                </select>
                                <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5 pointer-events-none" />
                            </div>
                            <p v-if="errors.department" class="text-sm text-red-500 font-medium">
                                {{ form.department && props.departmentsWithManager.includes(form.department) && form.department !== props.employee.department
                                    ? 'هذا القسم لديه مدير بالفعل!' 
                                    : 'الرجاء اختيار القسم.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- معلومات الإتصال والحالة -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:phone-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الإتصال والحالة
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="phone" class="text-gray-700 font-bold">رقم الهاتف</Label>
                            <div class="relative">
                                <Input
                                    required
                                    id="phone"
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
                            <Label for="email" class="text-gray-700 font-bold">البريد الإلكتروني</Label>
                            <div class="relative">
                                <Input
                                    required
                                    id="email"
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

                        <!-- حالة الحساب -->
                        <div class="space-y-2 sm:col-span-2">
                            <Label class="text-gray-700 font-bold block mb-2">حالة الحساب</Label>
                            <div class="flex gap-4">
                                <label class="cursor-pointer relative">
                                    <input type="radio" v-model="form.isActive" :value="true" class="peer sr-only" />
                                    <div class="px-6 py-3 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700 transition-all flex items-center gap-2">
                                        <Icon icon="solar:check-circle-bold" class="w-5 h-5" />
                                        <span class="font-bold">مفعل</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer relative">
                                    <input type="radio" v-model="form.isActive" :value="false" class="peer sr-only" />
                                    <div class="px-6 py-3 rounded-xl border-2 border-gray-200 bg-white text-gray-500 peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700 transition-all flex items-center gap-2">
                                        <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                        <span class="font-bold">معطل</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="$emit('close')"
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
                        حفظ التعديلات
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
                    icon="solar:pen-new-square-bold-duotone"
                    class="w-10 h-10 text-yellow-500"
                />
            </div>
            
            <h3 class="text-xl font-bold text-[#2E5077] mb-3">
                تأكيد تعديل البيانات
            </h3>
            
            <p class="text-gray-600 mb-8 leading-relaxed">
                هل أنت متأكد من رغبتك في حفظ التعديلات للموظف <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
            </p>
            
            <div class="flex gap-3 justify-center">
                <button
                    @click="closeConfirmationModal"
                    class="px-6 py-2.5 rounded-xl text-gray-600 font-bold hover:bg-gray-100 transition-all duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmUpdate"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
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