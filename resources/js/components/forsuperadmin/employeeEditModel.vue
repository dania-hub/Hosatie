<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";
import Input from "@/components/ui/input/Input.vue";

// إعداد axios
const api = axios.create({
    baseURL: '/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
    config => {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

const props = defineProps({
    isOpen: Boolean,
    employee: Object,
    hasWarehouseManager: Boolean,
    availableDepartments: Array,
    availableRoles: Array,
    departmentsWithManager: Array,
    availableHospitals: Array // إضافة المستشفيات المتاحة
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
    hospital: "", // إضافة حقل المستشفى
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
    hospital: false, // إضافة خطأ للمستشفى
});

// حالة التحقق من رقم الهاتف
const phoneExists = ref(false);
const checkingPhone = ref(false);

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
            hospital: props.employee.hospital || "", // تعيين قيمة المستشفى
            isActive: props.employee.isActive,
        };
        phoneExists.value = false;
    }
}, { immediate: true });

// إنشاء قائمة الأقسام المتاحة
const filteredDepartments = computed(() => {
    if (!props.availableDepartments) return [];
    
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

    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    const isValidFormat = phoneRegex.test(data.phone.trim());
    errors.value.phone = !isValidFormat;
    if (errors.value.phone) isValid = false;
    // التحقق من وجود الرقم فقط إذا كان مختلفاً عن الرقم الأصلي
    if (isValidFormat && data.phone !== props.employee.phone && phoneExists.value) {
        isValid = false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    errors.value.email = !emailRegex.test(data.email.trim());
    if (errors.value.email) isValid = false;

    const roleList = getRoleList.value;
    errors.value.role = !data.role || !roleList.includes(data.role);
    if (errors.value.role) isValid = false;

    if (isDepartmentManagerRole(data.role)) {
        errors.value.department = !data.department;
        if (errors.value.department) isValid = false;
        
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

    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    if (!phoneRegex.test(data.phone.trim())) return false;
    // التحقق من وجود الرقم فقط إذا كان مختلفاً عن الرقم الأصلي
    if (data.phone !== props.employee.phone && phoneExists.value) return false;

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

    // التحقق من حقل المستشفى
    if (!data.hospital || !props.availableHospitals?.includes(data.hospital)) {
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
        hospital: props.employee.hospital || "", // إضافة المستشفى للمقارنة
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
        hospital: form.value.hospital, // إضافة المستشفى
    };
    
    emit('save', updatedEmployee);
    closeConfirmationModal();
    emit('close');
};

// التحقق من وجود رقم الهاتف
const checkPhoneExists = async (phone, originalPhone) => {
    if (!phone || phone.trim() === "") {
        phoneExists.value = false;
        return;
    }

    // إذا كان الرقم هو نفس الرقم الأصلي، لا حاجة للتحقق
    if (phone.trim() === originalPhone) {
        phoneExists.value = false;
        return;
    }

    // التحقق من التنسيق أولاً
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    if (!phoneRegex.test(phone.trim())) {
        phoneExists.value = false;
        return;
    }

    checkingPhone.value = true;
    try {
        const response = await api.get(`/super-admin/users/check-phone/${phone.trim()}`);
        if (response.data && response.data.data) {
            phoneExists.value = response.data.data.exists;
        }
    } catch (error) {
        console.error("Error checking phone:", error);
        phoneExists.value = false;
    } finally {
        checkingPhone.value = false;
    }
};

// متغير للـ timeout
let phoneCheckTimeout = null;

// مراقبة تغييرات رقم الهاتف
watch(() => form.value.phone, (newPhone) => {
    // التحقق من الصيغة فوراً
    if (newPhone && newPhone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        errors.value.phone = !phoneRegex.test(newPhone.trim());
    } else {
        errors.value.phone = false;
    }
    
    // التحقق من وجود الرقم بعد 500ms (فقط إذا كان مختلفاً عن الأصلي)
    if (phoneCheckTimeout) {
        clearTimeout(phoneCheckTimeout);
    }
    
    phoneCheckTimeout = setTimeout(() => {
        checkPhoneExists(newPhone, props.employee?.phone || "");
    }, 500); // انتظار 500ms بعد توقف المستخدم عن الكتابة
});

watch(() => form.value.role, (newRole) => {
    if (!isDepartmentManagerRole(newRole)) {
        form.value.department = "";
    }
});
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
                                <option value="" disabled>اختر الدور الوظيفي</option>
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
                                <option value="" disabled>اختر المستشفى</option>
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

                    
          

                    <!-- Phone -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="0211234567 أو 0921234567"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.phone || phoneExists, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.phone && !phoneExists }"
                            class="bg-white focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.phone && form.phone && !phoneExists" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            رقم الهاتف غير صحيح (يجب أن يبدأ بـ 021/092/091/093/094 متبوعاً بـ 7 أرقام)
                        </p>
                        <p v-if="phoneExists && form.phone && !errors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            رقم الهاتف موجود بالفعل
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

                    <!-- Account Status -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2 mb-2">
                            <Icon icon="solar:shield-user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            حالة الحساب
                        </label>
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
                    <Icon icon="solar:pen-new-square-bold-duotone" class="w-10 h-10 text-[#4DA1A9]" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد تعديل البيانات</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في حفظ التعديلات للموظف <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
                    <br>
                    <span class="text-sm text-[#4DA1A9] mt-2 block">
                        المستشفى: <span class="font-bold">{{ form.hospital }}</span>
                    </span>
                </p>
            </div>
            <div class="flex justify-center bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeConfirmationModal" 
                    class=" px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmUpdate" 
                    class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
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