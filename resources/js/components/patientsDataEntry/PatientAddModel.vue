<script setup>
import { ref, computed, watch } from "vue";
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean
});

const emit = defineEmits(['close', 'save']);

// نموذج البيانات
const form = ref({
    nationalId: "",
    fullName: "",
    birthDate: "",
    phone: "",
    email: "",
});

// أخطاء التحقق
const errors = ref({
    nationalId: false,
    fullName: false,
    birthDate: false,
    phone: false,
    email: false,
});

// حالة نافذة التأكيد
const isConfirmationModalOpen = ref(false);

// إعادة تعيين النموذج
const resetForm = () => {
    form.value = { 
        nationalId: "", 
        fullName: "", 
        birthDate: "", 
        phone: "", 
        email: "" 
    };
    errors.value = { 
        nationalId: false, 
        fullName: false, 
        birthDate: false, 
        phone: false, 
        email: false 
    };
};

// التحقق من صحة النموذج
const validateForm = () => {
    let isValid = true;
    const data = form.value;

    const nationalIdRegex = /^\d{12}$/;
    errors.value.nationalId = !nationalIdRegex.test(data.nationalId);
    if (errors.value.nationalId) isValid = false;

    const fullNameRegex = /^[\u0600-\u06FFa-zA-Z\s]{3,}$/;
    errors.value.fullName = !fullNameRegex.test(data.fullName.trim());
    if (errors.value.fullName) isValid = false;

    errors.value.birthDate = !data.birthDate;
    if (errors.value.birthDate) isValid = false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/;
    errors.value.phone = !phoneRegex.test(data.phone.trim());
    if (errors.value.phone) isValid = false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    errors.value.email = !emailRegex.test(data.email.trim());
    if (errors.value.email) isValid = false;

    return isValid;
};

// التحقق من صحة النموذج (computed)
const isFormValid = computed(() => {
    const data = form.value;
    
    const nationalIdRegex = /^\d{12}$/;
    if (!nationalIdRegex.test(data.nationalId)) return false;

    const fullNameRegex = /^[\u0600-\u06FFa-zA-Z\s]{3,}$/;
    if (!fullNameRegex.test(data.fullName.trim())) return false;

    if (!data.birthDate) return false;

    const phoneRegex = /^(002189|09|\+2189)[1-6]\d{7}$/;
    if (!phoneRegex.test(data.phone.trim())) return false;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email.trim())) return false;

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
    const newPatient = {
        name: form.value.fullName,
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

// إعادة تعيين النموذج عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        resetForm();
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
                            id="national-id"
                            v-model="form.nationalId"
                            placeholder="أدخل الرقم الوطني (12 رقم)"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.nationalId }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.nationalId" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال الرقم الوطني بشكل صحيح (12 رقم)
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
                            v-model="form.fullName"
                            placeholder="أدخل الاسم الرباعي"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': errors.fullName }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="errors.fullName" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            الرجاء إدخال الاسم الرباعي بشكل صحيح
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
                                id="birth-date"
                                type="date" 
                                :max="maxDate" 
                                v-model="form.birthDate"
                                :class="{ 'border-red-500 hover:border-red-500': errors.birthDate, 'border-[#B8D7D9] focus:border-[#4DA1A9] hover:border-[#4DA1A9]': !errors.birthDate }"
                                class="h-9 text-right w-full pr-3 appearance-none rounded-2xl bg-white"
                            />
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                <Icon 
                                    icon="solar:calendar-linear" 
                                    class="w-5 h-5 transition-colors duration-200"
                                    :class="errors.birthDate ? 'text-red-500' : 'text-[#79D7BE]'"
                                />
                            </div>
                        </div>
                        <p v-if="errors.birthDate" class="text-xs text-red-500 mt-1 flex items-center gap-1">
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
                    <div class="space-y-2 md:col-span-2">
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
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeConfirmationModal" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    مراجعة
                </button>
                <button 
                    @click="confirmRegistration" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#2E5077] text-white font-medium hover:bg-[#1a2f4d] transition-colors duration-200 shadow-lg shadow-[#2E5077]/20"
                >
                    تأكيد وإنشاء
                </button>
            </div>
        </div>
    </div>
</template>