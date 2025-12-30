<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

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
    (config) => {
        const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

const props = defineProps({
    isOpen: Boolean,
    availableManagers: Array
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    name: "",
    code: "",
    address: "",
    city: "",
    phone: "",
    managerId: "",
});

// أخطاء التحقق
const errors = ref({
    name: false,
    code: false,
    city: false,
    phone: false,
});

// حالة التحقق من رقم الهاتف
const phoneExists = ref(false);
const checkingPhone = ref(false);
const phoneMessage = ref("");

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// إعادة تعيين النموذج
const resetForm = () => {
    form.value = { 
        name: "",
        code: "",
        address: "",
        city: "",
        phone: "",
        managerId: "",
    };
    errors.value = { 
        name: false,
        code: false,
        city: false,
        phone: false,
    };
    phoneExists.value = false;
    phoneMessage.value = "";
};

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    errors.value.name = !data.name || data.name.trim().length < 2;
    if (errors.value.name) isValid = false;

    errors.value.code = !data.code || data.code.trim().length < 1;
    if (errors.value.code) isValid = false;

    errors.value.city = !data.city || (data.city !== 'طرابلس' && data.city !== 'بنغازي');
    if (errors.value.city) isValid = false;

    // رقم الهاتف اختياري ولكن إذا تم إدخاله يجب أن يكون صالحاً
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        const isValidFormat = phoneRegex.test(data.phone.trim());
        errors.value.phone = !isValidFormat;
        if (errors.value.phone) isValid = false;
        if (!isValidFormat || phoneExists.value) isValid = false;
    } else {
        errors.value.phone = false;
    }

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    if (!data.name || data.name.trim().length < 2) return false;
    if (!data.code || data.code.trim().length < 1) return false;
    if (!data.city || (data.city !== 'طرابلس' && data.city !== 'بنغازي')) return false;
    
    // التحقق من رقم الهاتف إذا كان موجوداً
    if (data.phone && data.phone.trim() !== "") {
        const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
        if (!phoneRegex.test(data.phone.trim()) || phoneExists.value) return false;
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
    const newSupplier = {
        name: form.value.name,
        code: form.value.code,
        address: form.value.address,
        city: form.value.city,
        phone: form.value.phone || null,
        managerId: form.value.managerId || null,
    };
    
    emit('save', newSupplier);
    closeConfirmationModal();
    closeModal();
};

// إغلاق النافذة
const closeModal = () => {
    resetForm();
    emit('close');
};

// التحقق من وجود رقم الهاتف
const checkPhoneExists = async (phone) => {
    if (!phone || phone.trim() === "") {
        phoneExists.value = false;
        phoneMessage.value = "";
        return;
    }

    // التحقق من التنسيق أولاً
    const phoneRegex = /^(021|092|091|093|094)\d{7}$/;
    if (!phoneRegex.test(phone.trim())) {
        phoneExists.value = false;
        phoneMessage.value = "";
        return;
    }

    checkingPhone.value = true;
    try {
        const response = await api.get(`/super-admin/suppliers/check-phone/${phone.trim()}`);
        if (response.data && response.data.data) {
            phoneExists.value = response.data.data.exists;
            phoneMessage.value = response.data.data.exists ? "رقم الهاتف موجود بالفعل في النظام" : "";
        }
    } catch (error) {
        console.error("Error checking phone:", error);
        phoneExists.value = false;
        phoneMessage.value = "";
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
    
    // التحقق من وجود الرقم بعد 500ms
    if (phoneCheckTimeout) {
        clearTimeout(phoneCheckTimeout);
    }
    
    phoneCheckTimeout = setTimeout(() => {
        checkPhoneExists(newPhone);
    }, 500); // انتظار 500ms بعد توقف المستخدم عن الكتابة
});

// إعادة تعيين النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        resetForm();
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
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:box-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    إضافة مورد جديد
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- اسم المورد -->
                    <div class="space-y-2">
                        <Label for="name" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المورد
                        </Label>
                        <div class="relative">
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="أدخل اسم المورد"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.name, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.name }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال اسم المورد (على الأقل حرفين)
                        </p>
                    </div>

                    <!-- كود المورد -->
                    <div class="space-y-2">
                        <Label for="code" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hashtag-circle-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            كود المورد
                        </Label>
                        <div class="relative">
                            <Input
                                id="code"
                                v-model="form.code"
                                placeholder="أدخل كود المورد"
                                type="text"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.code, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.code }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                        <p v-if="errors.code" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال كود المورد
                        </p>
                    </div>

                    <!-- المدينة -->
                    <div class="space-y-2">
                        <Label for="city" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:city-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المدينة
                        </Label>
                        <div class="relative">
                            <select
                                id="city"
                                v-model="form.city"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.city, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.city }"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">اختر المدينة</option>
                                <option value="طرابلس">طرابلس</option>
                                <option value="بنغازي">بنغازي</option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="errors.city" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء اختيار المدينة
                        </p>
                    </div>

                    <!-- العنوان -->
                    <div class="space-y-2">
                        <Label for="address" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:map-point-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            العنوان (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="address"
                                v-model="form.address"
                                placeholder="أدخل العنوان"
                                type="text"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                        </div>
                    </div>

                    <!-- رقم الهاتف -->
                    <div class="space-y-2">
                        <Label for="phone" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف (اختياري)
                        </Label>
                        <div class="relative">
                            <Input
                                id="phone"
                                v-model="form.phone"
                                placeholder="مثال: 0211234567"
                                type="tel"
                                maxlength="10"
                                :class="{ 'border-red-500 focus:ring-red-500/20': errors.phone || phoneExists, 'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !errors.phone && !phoneExists }"
                                class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                            />
                            <span v-if="checkingPhone" class="absolute left-3 top-2.5 text-xs text-gray-400">
                                جاري التحقق...
                            </span>
                        </div>
                        <p v-if="errors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            يجب أن يبدأ الرقم بـ 021 أو 092 أو 091 أو 093 أو 094 متبوعاً بـ 7 أرقام
                        </p>
                        <p v-if="phoneExists && !errors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ phoneMessage }}
                        </p>
                    </div>

                    <!-- فاصل -->
                    <div class="md:col-span-2 border-t border-gray-200 my-2"></div>

                    <!-- المسؤول -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="manager" class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            المسؤول (اختياري)
                        </Label>
                        <div class="relative">
                            <select
                                id="manager"
                                v-model="form.managerId"
                                class="h-10 text-right w-full rounded-xl bg-white border border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 focus:ring-2 transition-all px-4 appearance-none focus:outline-none"
                            >
                                <option value="">اختياري - بدون مسؤول</option>
                                <option v-for="manager in props.availableManagers" 
                                        :key="manager.id" 
                                        :value="manager.id">
                                    {{ manager.name }}
                                </option>
                            </select>
                            <Icon icon="solar:alt-arrow-down-bold" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
                        </div>
                        <p v-if="form.managerId" class="text-xs text-gray-500 mt-1">
                            المسؤول المختار: 
                            <span class="font-semibold text-[#4DA1A9]">
                                {{ props.availableManagers.find(m => m.id === form.managerId)?.name }}
                            </span>
                        </p>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="!isFormValid"
                        :class="[
                            'px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200',
                            isFormValid 
                                ? 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1' 
                                : 'bg-gray-300 cursor-not-allowed shadow-none'
                        ]"
                    >
                        <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                        حفظ المورد
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة التأكيد -->
    <div v-if="isConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon
                        icon="solar:shield-warning-bold-duotone"
                        class="w-10 h-10 text-[#4DA1A9]"
                    />
                </div>
                
                <h3 class="text-xl font-bold text-[#2E5077]">
                    تأكيد إضافة المورد
                </h3>
                
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من رغبتك في إنشاء المورد الجديد باسم
                    <span class="font-bold text-[#2E5077]">"{{ form.name }}"</span>؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">سيتم إنشاء مورد جديد في النظام</span>
                </p>
            </div>
            
            <div class="flex justify-center bg-gray-50 px-6 py-4 gap-3 border-t border-gray-100">
                <button
                    @click="closeConfirmationModal"
                    class="px-15 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmRegistration"
                    class="px-15 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-1"
                >
                    تأكيد الإضافة
                </button>
            </div>
        </div>
    </div>
</template>
