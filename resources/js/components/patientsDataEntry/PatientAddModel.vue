<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

import axios from 'axios';

const props = defineProps({
    isOpen: Boolean,
});

const emit = defineEmits(['close', 'save']);

const form = ref({
    nationalId: "",
    name: "",
    birthDate: "",
    phone: "",
    email: "",
});

// أخطاء التحقق
const errors = ref({
    nationalId: false,
    name: false,
    birthDate: false,
    phone: false,
    email: false,
});

const duplicateError = ref("");
let debounceTimeout = null;

// Helper to get headers with token
const getAuthHeaders = () => {
    const token = localStorage.getItem('auth_token');
    return {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    };
};

const minDate = "1900-01-01";
const maxDate = computed(() => {
    const today = new Date();
    today.setDate(today.getDate() - 1); // Set to yesterday
    return today.toISOString().split('T')[0];
});

const checkUniqueness = async () => {
    // Clear previous timeout
    if (debounceTimeout) clearTimeout(debounceTimeout);
    
    // Clear error initially
    duplicateError.value = "";

    // Validation prerequisites for check
    const nationalId = form.value.nationalId;
    const phone = form.value.phone;
    
    // Quick invalid check
    if ((!nationalId || nationalId.length < 12) && (!phone || phone.length < 10)) return;

    debounceTimeout = setTimeout(async () => {
        try {
            const response = await axios.post('/api/data-entry/patients/check-unique', {
                national_id: nationalId.length === 12 ? nationalId : null,
                phone: phone.length === 10 ? phone : null
            }, getAuthHeaders());

            if (response.data.exists) {
                duplicateError.value = response.data.message;
            }
        } catch (error) {
            console.error("Check unique error", error);
        }
    }, 500); // 500ms debounce
};

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);
 // التحقق من صحة الرقم الوطني أثناء الكتابة
const validateNationalIdInput = () => {
    // إزالة جميع الأحرف غير الرقمية
    form.value.nationalId = form.value.nationalId.replace(/\D/g, '');
    
    // تقليل الرقم إلى 12 رقم كحد أقصى
    if (form.value.nationalId.length > 12) {
        form.value.nationalId = form.value.nationalId.substring(0, 12);
    }

    // Strict validation
    if (form.value.nationalId.length > 0) {
        if (!/^[12]/.test(form.value.nationalId)) {
            errors.value.nationalId = 'يجب أن يبدأ الرقم الوطني بـ 1 أو 2';
        } else if (form.value.nationalId.length < 12) {
            errors.value.nationalId = 'الرقم الوطني يجب أن يتكون من 12 رقم';
        } else {
            errors.value.nationalId = "";
        }
    } else {
        errors.value.nationalId = "";
    }

    checkUniqueness();
};

// التحقق من صحة رقم الهاتف أثناء الكتابة
const validatePhoneInput = () => {
    // إزالة جميع الأحرف غير الرقمية
    form.value.phone = form.value.phone.replace(/\D/g, '');
    
    // تقليل الرقم إلى 10 أرقام كحد أقصى
    if (form.value.phone.length > 10) {
        form.value.phone = form.value.phone.substring(0, 10);
    }
    
    // Strict validation
    if (form.value.phone.length > 0) {
        const validPrefixes = ['091', '092', '093', '094'];
        const hasValidPrefix = validPrefixes.some(prefix => form.value.phone.startsWith(prefix));

        if (!hasValidPrefix && form.value.phone.length >= 3) {
            errors.value.phone = 'يجب أن يبدأ الرقم بـ 091, 092, 093, 094';
        } else if (form.value.phone.length < 10) {
            errors.value.phone = 'رقم الهاتف يجب أن يتكون من 10 أرقام';
        } else {
            errors.value.phone = "";
        }
    } else {
        errors.value.phone = "";
    }

    checkUniqueness();
};

const validateNameInput = () => {
    const nameValue = form.value.name.trim();
    const spaceCount = (nameValue.match(/\s+/g) || []).length;
    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
    
    if (nameValue.length > 0) {
        if (!nameRegex.test(nameValue) || spaceCount < 3) {
            errors.value.name = 'الاسم يجب أن يكون رباعياً على الأقل ويحوي حروفاً فقط';
        } else {
            errors.value.name = "";
        }
    } else {
        errors.value.name = "";
    }
};

// التحقق من صحة البيانات النهائية
const validateForm = () => {
    const data = form.value;
    let isValid = true;
    
    // Reset errors
    errors.value = {
        nationalId: "",
        name: "",
        birthDate: "",
        phone: "",
        email: "",
    };
    
    // 1. National ID Validation
    const nidRegex = /^[12]\d{11}$/;
    if (!data.nationalId) {
        errors.value.nationalId = 'الرقم الوطني مطلوب';
        isValid = false;
    } else if (!nidRegex.test(data.nationalId)) {
        errors.value.nationalId = 'الرقم الوطني غير صحيح';
        isValid = false;
    }

    const nameValue = data.name ? data.name.trim() : "";
    const spaceCount = (nameValue.match(/\s+/g) || []).length;
    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
    
    if (!data.name) {
        errors.value.name = 'الاسم مطلوب';
        isValid = false;
    } else if (!nameRegex.test(nameValue) || spaceCount < 3) {
        errors.value.name = 'يجب إدخال الاسم الرباعي على الأقل';
        isValid = false;
    }

    if (!data.birthDate) {
        errors.value.birthDate = 'تاريخ الميلاد مطلوب';
        isValid = false;
    }
// ...
    // Prevent submit if duplicate found
    if (duplicateError.value) return false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    const nationalIdRegex = /^[12]\d{11}$/;
    if (!nationalIdRegex.test(data.nationalId)) return false;

    const nameValue = data.name ? data.name.trim() : "";
    const spaceCount = (nameValue.match(/\s+/g) || []).length;
    const nameRegex = /^[\u0600-\u06FFa-zA-Z\s]+$/;
    if (!data.name || !nameRegex.test(nameValue) || spaceCount < 3) return false;

    if (!data.birthDate) return false;

    const phoneRegex = /^(09[1-4])\d{7}$/; 
    if (!phoneRegex.test(data.phone.trim())) return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (data.email && data.email.trim() !== '' && !emailRegex.test(data.email.trim())) return false;

    if (duplicateError.value) return false;

    return true;
});
// ... 
const resetForm = () => {
    form.value = { 
        nationalId: "", 
        name: "", 
        birthDate: "", 
        phone: "", 
        email: "" 
    };
    errors.value = { 
        nationalId: false, 
        name: false, 
        birthDate: false, 
        phone: false, 
        email: false 
    };
};
// ...
// فتح نافذة التأكيد
const submitForm = () => {
    if (validateForm()) {
        isConfirmationModalOpen.value = true;
    }
};

const closeConfirmationModal = () => {
    isConfirmationModalOpen.value = false;
};

// تأكيد التسجيل
const confirmRegistration = () => {
    const newPatient = {
        name: form.value.name,
        nationalId: form.value.nationalId,
        birth: form.value.birthDate.replace(/-/g, "/"),
        phone: form.value.phone,
        email: form.value.email,
    };
    
    emit('save', newPatient);
    closeConfirmationModal();
    closeModal();
};

// إغلاق النافذة
const closeModal = () => {
    resetForm();
    emit('close');
};

const dateInput = ref(null);
const openDatePicker = () => {
    if (dateInput.value) {
        // هذه الدالة تفتح منقي التاريخ في المتصفحات الحديثة
        dateInput.value.showPicker(); 
    }
};

// إعادة تعيين النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        resetForm();
    }
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
                        <Icon icon="mingcute:user-add-line" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تسجيل مريض جديد
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
                            id="nationalId"
                            v-model="form.nationalId"
                            placeholder="XXXXXXXXXXXX"
                            maxlength="12"
                            @input="validateNationalIdInput"
                            :class="[
                                'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right font-medium',
                                (errors.nationalId || (duplicateError && duplicateError.includes('الرقم'))) ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
                            ]"
                        />
                        <div v-if="duplicateError && duplicateError.includes('الرقم')" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-bold animate-pulse">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ duplicateError }}
                        </div>
                        <p v-else-if="errors.nationalId" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errors.nationalId }}
                        </p>
                    </div>

                    <!-- Full Name -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الاسم الرباعي
                        </label>
                        <Input
                            id="full-name"
                            v-model="form.name"
                            placeholder="أدخل الاسم الرباعي"
                            @input="validateNameInput"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.name }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.name" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ errors.name }}
                        </p>
                    </div>

                    <!-- Birth Date -->
                   <div class="space-y-2" dir="rtl">
    <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
        <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
        تاريخ الميلاد
    </label>
    
    <div class="relative w-full">
        <input
            id="birth-date"
            ref="dateInput"
            type="date" 
            :max="maxDate"
            :min="minDate"
            v-model="form.birthDate"
            :class="[
                'h-9 text-right w-full pl-3 pr-10 appearance-none rounded-2xl bg-white cursor-pointer',
                'border focus:outline-none transition-colors duration-200',
                errors.birthDate 
                    ? 'border-red-500 hover:border-red-500 focus:border-red-500' 
                    : 'border-gray-200 hover:border-[#4DA1A9] focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9]/20'
            ]"
            @change="errors.birthDate = ''"
        />
        
        <div 
            class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer" 
            @click="openDatePicker"
        >
            <Icon 
                icon="solar:calendar-linear" 
                class="w-5 h-5 transition-colors duration-200"
                :class="errors.birthDate ? 'text-red-500' : 'text-[#79D7BE]'"
            />
        </div>
    </div>

    <p v-if="errors.birthDate" class="text-xs text-red-500 mt-1 flex items-center gap-1">
        <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
        {{ errors.birthDate }}
    </p>
</div>

                    <!-- Phone -->
                    <div class="space-y-2" dir="rtl">
    <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
        <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
        رقم الهاتف
    </label>
    <Input
        id="phone"
        v-model="form.phone"
        type="text"
        placeholder="09XXXXXXXX"
        maxlength="10"
        @input="validatePhoneInput"
        :class="[
            'bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right',
            (errors.phone || (duplicateError && duplicateError.includes('الهاتف'))) ? '!border-red-500 !focus:border-red-500 !focus:ring-red-500/20' : ''
        ]"
    />
    <div v-if="duplicateError && duplicateError.includes('الهاتف')" class="text-xs text-red-500 mt-1 flex items-center gap-1 font-bold animate-pulse">
        <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
        {{ duplicateError }}
    </div>
    <p v-else-if="errors.phone" class="text-xs text-red-500 flex items-center gap-1">
        <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
        {{ errors.phone }}
    </p>
</div>

                    <!-- Email -->
                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني <span class="text-gray-400 text-xs">(اختياري)</span>
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
                            {{ errors.email }}
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
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-0.5"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    حفظ البيانات
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
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد التسجيل</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من صحة البيانات المدخلة؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">سيتم إنشاء ملف جديد للمريض</span>
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