<script setup>
import { ref, watch, computed, nextTick } from "vue";
import { Icon } from "@iconify/vue";
import Input from "@/components/ui/input/Input.vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object,
});

const emit = defineEmits(['close', 'save']);

const isEditConfirmationModalOpen = ref(false);

// البيانات الأصلية للمقارنة
const originalEditForm = ref({});

const editForm = ref({
    fileNumber: "",
    nationalId: "",
    fullName: "",
    birthDate: "",
    phone: "",
    email: "",
});

const editErrors = ref({
    birthDate: false,
    phone: false,
    email: false,
});

// مرجع لحقل التاريخ
const editDateInput = ref(null);

// فتح منتقي التاريخ عند النقر على الأيقونة
const openEditDatePicker = () => {
    if (editDateInput.value) {
        editDateInput.value.showPicker();
    }
};

// التحقق من صحة رقم الهاتف أثناء الكتابة
const validateEditPhoneInput = () => {
    // إزالة جميع الأحرف غير الرقمية
    editForm.value.phone = editForm.value.phone.replace(/\D/g, '');
    
    // تقليل الرقم إلى 10 أرقام كحد أقصى
    if (editForm.value.phone.length > 10) {
        editForm.value.phone = editForm.value.phone.substring(0, 10);
    }
    
    const phone = editForm.value.phone;
    const validPrefixes = ['091', '092', '093', '094'];
    
    if (phone.length > 0) {
        const hasValidPrefix = validPrefixes.some(prefix => phone.startsWith(prefix));
        if (!hasValidPrefix && phone.length >= 3) {
            editErrors.value.phone = 'يجب أن يبدأ الرقم بـ 091, 092, 093, 094';
        } else if (phone.length < 10) {
            editErrors.value.phone = 'رقم الهاتف يجب أن يتكون من 10 أرقام';
        } else {
            editErrors.value.phone = '';
        }
    } else {
        editErrors.value.phone = '';
    }
};

// التحقق من صحة البيانات النهائية
const validateEditForm = () => {
    let isValid = true;
    editErrors.value = {
        birthDate: false,
        phone: false,
        email: false,
    };

    if (!editForm.value.birthDate) {
        editErrors.value.birthDate = true;
        isValid = false;
    }

    const phoneRegex = /^(09[1-4])\d{7}$/;
    if (!editForm.value.phone || !phoneRegex.test(editForm.value.phone)) {
        editErrors.value.phone = 'رقم الهاتف غير صحيح (يجب أن يبدأ بـ 091-094 ويتكون من 10 أرقام)';
        isValid = false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!editForm.value.email || !emailRegex.test(editForm.value.email)) {
        editErrors.value.email = true;
        isValid = false;
    }

    return isValid;
};

// التحقق من وجود تغييرات
const hasChanges = computed(() => {
    return JSON.stringify(editForm.value) !== JSON.stringify(originalEditForm.value);
});

// فتح نافذة التأكيد
const openEditConfirmationModal = () => {
    if (validateEditForm()) {
        isEditConfirmationModalOpen.value = true;
    }
};

// تأكيد التعديل
const confirmEdit = () => {
    const updatedPatient = {
        fileNumber: editForm.value.fileNumber,
        name: editForm.value.fullName,
        nationalId: editForm.value.nationalId,
        birth: editForm.value.birthDate.replace(/-/g, "/"),
        phone: editForm.value.phone,
        email: editForm.value.email,
        id: props.patient.id // Include ID for API update
    };
    
    emit('save', updatedPatient);
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

// تهيئة البيانات عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.patient) {
        const formattedBirthDate = props.patient.birth ? props.patient.birth.replace(/\//g, "-") : '';

        const initialData = {
            fileNumber: props.patient.fileNumber,
            nationalId: props.patient.nationalId,
            fullName: props.patient.name,
            birthDate: formattedBirthDate,
            phone: props.patient.phone,
            email: props.patient.email ,
        };

        originalEditForm.value = { ...initialData };
        editForm.value = initialData;

        // إعادة تعيين الأخطاء
        editErrors.value = {
            birthDate: false,
            phone: false,
            email: false,
        };
        
        // تحديث مرجع حقل التاريخ بعد تهيئة البيانات
        nextTick(() => {
            editDateInput.value = document.getElementById('edit-birth-date');
        });
    }
});

const maxDate = computed(() => {
    const today = new Date();
    today.setDate(today.getDate() - 1);
    return today.toISOString().split('T')[0];
});
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeEditModal">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100">
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات المريض
                </h2>
                <button @click="closeEditModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
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
                                :value="editForm.fileNumber" 
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
                                :value="editForm.nationalId" 
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
                    <!-- Full Name -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            الاسم الرباعي
                        </label>
                        <Input
                            id="edit-fullName"
                            v-model="editForm.fullName"
                            placeholder="أدخل الاسم الرباعي"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                    </div>

                    <!-- Birth Date -->
                    <div class="space-y-2" dir="rtl">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            تاريخ الميلاد
                        </label>
                        
                        <div class="relative w-full">
                            <input
                                id="edit-birth-date"
                                ref="editDateInput"
                                type="date" 
                                :max="maxDate"
                                v-model="editForm.birthDate"
                                :class="[
                                    'h-9 text-right w-full pl-3 pr-10 appearance-none rounded-2xl bg-white cursor-pointer',
                                    'border focus:outline-none transition-colors duration-200',
                                    editErrors.birthDate 
                                        ? 'border-red-500 hover:border-red-500 focus:border-red-500' 
                                        : 'border-gray-200 hover:border-[#4DA1A9] focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9]/20'
                                ]"
                                @change="editErrors.birthDate = ''"
                            />
                            
                            <div 
                                class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer" 
                                @click="openEditDatePicker"
                            >
                                <Icon 
                                    icon="solar:calendar-linear" 
                                    class="w-5 h-5 transition-colors duration-200"
                                    :class="editErrors.birthDate ? 'text-red-500' : 'text-[#79D7BE]'"
                                />
                            </div>
                        </div>

                        <p v-if="editErrors.birthDate" class="text-xs text-red-500 mt-1 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            تاريخ الميلاد مطلوب
                        </p>
                    </div>

                    <!-- Phone -->
                    <div class="space-y-2" dir="rtl">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:phone-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الهاتف
                        </label>
                        <Input
                            id="edit-phone"
                            v-model="editForm.phone"
                            type="text"
                            placeholder="09XXXXXXXX"
                            maxlength="10"
                            @input="validateEditPhoneInput"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': editErrors.phone }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20 text-right"
                        />
                        <p v-if="editErrors.phone" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            {{ editErrors.phone }}
                        </p>
                    </div>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:letter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            البريد الإلكتروني
                        </label>
                        <Input
                            id="edit-email"
                            type="email"
                            v-model="editForm.email"
                            placeholder="example@domain.com"
                            :class="{ 'border-red-500 focus:border-red-500 focus:ring-red-500/20': editErrors.email }"
                            class="bg-white border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20"
                        />
                        <p v-if="editErrors.email" class="text-xs text-red-500 flex items-center gap-1">
                            <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                            البريد الإلكتروني غير صحيح
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100">
                <button 
                    @click="closeEditModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 flex items-center gap-2"
                >
                    إلغاء
                </button>
                <button 
                    @click="openEditConfirmationModal" 
                    :disabled="!hasChanges"
                    :class="[
                        'px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200',
                        hasChanges 
                            ? 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-0.5' 
                            : 'bg-gray-300 cursor-not-allowed shadow-none'
                    ]"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    حفظ التغييرات
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="isEditConfirmationModalOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeEditConfirmationModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:question-circle-bold-duotone" class="w-10 h-10 text-[#4DA1A9]" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد التعديل</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من حفظ التغييرات على بيانات المريض؟
                    <br>
                    <span class="text-sm text-[#4DA1A9]">لا يمكن التراجع عن هذه العملية</span>
                </p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="closeEditConfirmationModal" 
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmEdit" 
                    class="flex-1 px-4 py-2.5 rounded-xl bg-[#2E5077] text-white font-medium hover:bg-[#1a2f4d] transition-colors duration-200 shadow-lg shadow-[#2E5077]/20"
                >
                    تأكيد الحفظ
                </button>
            </div>
        </div>
    </div>
</template>