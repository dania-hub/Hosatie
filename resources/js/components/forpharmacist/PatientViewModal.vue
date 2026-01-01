<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'dispensation-record', 'delete-medication', 'confirm-dispensation']);

// متغير لإدارة حالة فتح/إغلاق حقل إدخال كمية الصرف لكل دواء
const dispensingStates = ref({});

// متغيرات جديدة لمودال التأكيد
const showConfirmationModal = ref(false);
const isSaving = ref(false);

// دالة لتبديل حالة فتح/إغلاق حقل الإدخال
const toggleDispensingInput = (medIndex) => {
    const drugKey = props.patient.medications[medIndex].drugName;
    if (!props.patient.medications[medIndex].dispensedQuantity) {
        // تعيين كمية افتراضية 1 عند الفتح لأول مرة
        props.patient.medications[medIndex].dispensedQuantity = 1; 
    }
    // إغلاق أي حقل مفتوح آخر
    Object.keys(dispensingStates.value).forEach(key => {
        if (key !== drugKey) {
            dispensingStates.value[key] = false;
        }
    });
    dispensingStates.value[drugKey] = !dispensingStates.value[drugKey];
};

// دالة لتنسيق الجرعة (استخدام النص الكامل من الـ API الذي يحتوي على الوحدة الصحيحة)
const formatDosage = (dosage) => {
    // إذا كان dosage نصاً جاهزاً (يحتوي على الوحدة مثل "5 حبة يومياً" أو "10 مل يومياً")
    if (typeof dosage === 'string') {
        return dosage;
    }
    // إذا كان رقم فقط أو قيمة غير صحيحة
    return dosage || 'غير محدد';
};

// حساب قائمة الأدوية المستحقة للصرف (بغض النظر عن تحديد الكمية)
const eligibleMedicationsList = computed(() => {
    if (!props.patient.medications) return [];
    return props.patient.medications.filter(med => 
        med.eligibilityStatus === 'مستحق'
    );
});

// حساب قائمة الأدوية التي سيتم صرفها فعلياً (التي تم تحديد كمية صرفها)
const dispensedMedicationsList = computed(() => {
    if (!props.patient.medications) return [];
    return props.patient.medications.filter(med => 
        med.eligibilityStatus === 'مستحق' && med.dispensedQuantity > 0
    );
});

// حساب عدد الأدوية المؤهلة للصرف (المستحقة) - جميع الأدوية المستحقة
const totalItemsToConfirm = computed(() => {
    return eligibleMedicationsList.value.length;
});

// فتح مودال التأكيد
const openConfirmationModal = () => {
    // التحقق من وجود أدوية تم تحديد كمية صرفها
    if (dispensedMedicationsList.value.length === 0) {
        emit('close');
        return;
    }
    showConfirmationModal.value = true;
};

// **تأكيد وإرسال إشارة الصرف (محدث)**
const handleConfirmation = () => {
    isSaving.value = true;
    
    // إرسال البيانات للمكون الأب للتعامل مع الـ API
    emit('confirm-dispensation', props.patient, dispensedMedicationsList.value);

    // إغلاق المودال محلياً بعد إرسال الحدث
    // المكون الأب سيعالج الـ API ثم يقوم بتحديث البيانات وإظهار رسالة النجاح
    setTimeout(() => {
        isSaving.value = false;
        showConfirmationModal.value = false;
        emit('close'); 
    }, 500); // تأخير بسيط لضمان إرسال الحدث
};

// دالة لحساب الكمية الشهرية للعرض (دائماً الجرعة اليومية * 30)
const getMonthlyQuantityDisplay = (med) => {
    // 1. محاولة الحصول على الكمية اليومية الرقمية
    let dailyQty = med.dailyQuantity || med.daily_quantity || med.daily_dosage;
    
    // 2. إذا لم تكن موجودة، نحاول استخراجها من نص الجرعة (مثال: "2 حبة يومياً" ← 2)
    if (dailyQty === undefined || dailyQty === null || dailyQty === 0) {
        const dosageText = String(med.dosage || '');
        const match = dosageText.match(/(\d+(?:\.\d+)?)/);
        if (match) {
            dailyQty = parseFloat(match[1]);
        }
    }
    
    // 3. الحساب والتحويل
    if (dailyQty && !isNaN(dailyQty)) {
        const monthlyAmount = Math.round(dailyQty * 30);
        // تحديد الوحدة
        let unit = med.unit;
        if (!unit) {
            const dosageText = String(med.dosage || '');
            if (dosageText.includes('مل')) unit = 'مل';
            else if (dosageText.includes('أمبول')) unit = 'أمبول';
            else if (dosageText.includes('جرام')) unit = 'جرام';
            else unit = 'حبة';
        }
        return `${monthlyAmount} ${unit}`;
    }
    
    // في حالة عدم توفر الجرعة اليومية، نعود للقيمة المخزنة أو "-"
    return med.monthlyQuantity || '-';
};

// إلغاء التأكيد
const cancelConfirmation = () => {
    showConfirmationModal.value = false;
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="!showConfirmationModal && $emit('close')">
        <div 
            v-if="!showConfirmationModal"
            class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[95vh] overflow-y-auto"
        >
            
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:stethoscope-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    ملف المريض - الصيدلية
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                
                <!-- Patient Info Section -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        البيانات الشخصية
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الاسم الرباعي</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nameDisplay }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الرقم الوطني</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nationalIdDisplay }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">تاريخ الميلاد</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.birthDisplay }}</div>
                        </div>
                    </div>
                </div>

                <!-- Medications Section -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            صرف حصة دوائية
                        </h3>
                        <button 
                            @click="$emit('dispensation-record')"
                            class="px-4 py-2 rounded-xl text-[#2E5077] bg-white border border-gray-200 font-medium hover:bg-gray-50 transition-colors duration-200 flex items-center gap-2 shadow-sm"
                        >
                            <Icon icon="solar:history-bold-duotone" class="w-5 h-5" />
                            سجل الصرف
                        </button>
                    </div>

                    <div v-if="patient.medications && patient.medications.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">إسم الدواء</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">الجرعة</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">الكمية الشهرية</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">أخر إستلام</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">بواسطة</th>
                                    <th class="p-4 text-sm font-bold text-[#2E5077]">حالة الإستحقاق</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(med, medIndex) in patient.medications" :key="medIndex" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-medium text-gray-700">
                                        <div class="flex flex-col">
                                            <span>{{ med.drugName }}</span>
                                            <span v-if="med.strength" class="text-sm text-gray-500 font-normal mt-1">{{ med.strength }}</span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-sm font-medium">
                                            {{ formatDosage(med.dosage) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ getMonthlyQuantityDisplay(med) }}</div>
                                            <div v-if="med.totalDispensedThisMonth > 0" class="text-xs space-y-0.5">
                                                <div class="text-orange-600">
                                                    مصروف: {{ med.totalDispensedThisMonth }} {{ med.unit || 'حبة' }}
                                                </div>
                                                <div v-if="med.remainingQuantity > 0" class="text-green-600 font-semibold">
                                                    متبقي: {{ med.remainingQuantity }} {{ med.unit || 'حبة' }}
                                                </div>
                                                <div v-else-if="med.remainingQuantity === 0 && med.monthlyQuantityNum > 0" class="text-gray-500">
                                                    تم صرف الكمية الشهرية كاملة
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignmentDate }}</td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignedBy || '-' }}</td>
                                    <td class="p-4">
                                        <div 
                                            class="flex items-center justify-start gap-2 cursor-pointer hover:bg-gray-100 p-2 rounded-lg transition-colors w-fit"
                                            @click="toggleDispensingInput(medIndex)"
                                        >
                                            <Icon 
                                                icon="solar:alt-arrow-down-bold"
                                                :class="[med.eligibilityStatus === 'مستحق' ? 'text-green-600 w-4 h-4' : 'text-gray-400 w-4 h-4', { 'rotate-180': dispensingStates[med.drugName] }]"
                                                class="transition-transform duration-200"
                                            />
                                            <span :class="{ 
                                                'text-green-600 font-bold bg-green-50 px-2 py-1 rounded-lg': med.eligibilityStatus === 'مستحق',
                                                'text-red-600 font-bold bg-red-50 px-2 py-1 rounded-lg': med.eligibilityStatus === 'غير متوفر',
                                                'text-gray-500 font-bold bg-gray-100 px-2 py-1 rounded-lg': med.eligibilityStatus === 'غير مستحق' 
                                            }" class="text-sm">
                                                {{ med.eligibilityStatus }}
                                            </span>
                                        </div>

                                        <div v-if="dispensingStates[med.drugName] && med.eligibilityStatus === 'مستحق'" class="mt-3 p-3 bg-gray-50 border border-gray-200 rounded-xl animate-in slide-in-from-top-2 duration-200">
                                            <label class="text-xs font-bold text-[#2E5077] block mb-2">كمية الصرف:</label>
                                            <input
                                                v-model.number="med.dispensedQuantity"
                                                type="number"
                                                min="1"
                                                :max="med.remainingQuantity > 0 ? med.remainingQuantity : med.monthlyQuantityNum"
                                                class="w-full h-9 text-center rounded-lg border border-gray-300 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all"
                                                placeholder="الكمية"
                                                @click.stop 
                                            />
                                            <div v-if="med.remainingQuantity > 0" class="mt-2 text-xs text-gray-500 text-center">
                                                الحد الأقصى: {{ med.remainingQuantity }} {{ med.unit || 'حبة' }}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center py-12 bg-white rounded-2xl border border-dashed border-gray-200">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                            <Icon icon="solar:pill-broken" class="w-8 h-8 text-gray-400" />
                        </div>
                        <p class="text-gray-500 font-medium">لا توجد أدوية مسجلة لهذا المريض</p>
                    </div>

                    <div v-if="totalItemsToConfirm > 0" class="mt-4 p-4 bg-[#4DA1A9]/10 border border-[#4DA1A9]/20 rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 bg-[#4DA1A9] rounded-full flex items-center justify-center text-white">
                            <Icon icon="solar:info-circle-bold" class="w-5 h-5" />
                        </div>
                        <p class="text-[#2E5077] font-medium">
                            يوجد <span class="font-bold text-[#4DA1A9]">{{ totalItemsToConfirm }}</span> دواء/أدوية مستحقة للصرف
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button 
                    @click="openConfirmationModal" 
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200"
                    :class="dispensedMedicationsList.length > 0 ? 'bg-[#4DA1A9] hover:bg-[#3a8c94] hover:-translate-y-0.5' : 'bg-gray-300 cursor-not-allowed shadow-none'"
                    :disabled="dispensedMedicationsList.length === 0"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    تأكيد الصرف
                </button>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="showConfirmationModal"
                class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            >
                <div
                    @click="cancelConfirmation"
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                ></div>

                <div
                    class="relative bg-white rounded-2xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] overflow-hidden transform transition-all scale-100"
                >
                    <div class="p-6 text-center space-y-4">
                        <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Icon icon="solar:question-circle-bold-duotone" class="w-10 h-10 text-[#4DA1A9]" />
                        </div>
                        <h3 class="text-xl font-bold text-[#2E5077]">تأكيد صرف الأدوية</h3>
                        <p class="text-gray-500 leading-relaxed">
                            هل أنت متأكد من صرف <span class="font-bold text-[#4DA1A9]">{{ totalItemsToConfirm }}</span> دواء للمريض <span class="font-bold text-[#2E5077]">{{ patient.nameDisplay || 'غير محدد' }}</span>؟
                        </p>
                        
                        <div v-if="patient.medications" class="w-full mb-2 max-h-40 overflow-y-auto bg-gray-50 rounded-xl p-2 border border-gray-100">
                            <div 
                                v-for="(med, index) in dispensedMedicationsList" 
                                :key="index"
                                class="flex justify-between items-center p-3 mb-2 bg-white rounded-lg shadow-sm last:mb-0"
                            >
                                <span class="text-sm font-bold text-[#2E5077]">{{ med.drugName }}</span>
                                <span class="text-xs bg-[#EAF3F4] text-[#4DA1A9] px-2 py-1 rounded-lg font-bold">{{ med.dispensedQuantity }} وحدة</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                        <button 
                            @click="cancelConfirmation" 
                            :disabled="isSaving"
                            class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                        >
                            إلغاء
                        </button>
                        <button 
                            @click="handleConfirmation" 
                            :disabled="isSaving"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-[#2E5077] text-white font-medium hover:bg-[#1a2f4d] transition-colors duration-200 shadow-lg shadow-[#2E5077]/20 flex justify-center items-center"
                        >
                            <Icon v-if="isSaving" icon="svg-spinners:ring-resize" class="w-5 h-5 ml-2 animate-spin" />
                            {{ isSaving ? 'جاري الإرسال...' : 'تأكيد وحفظ' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style>
/* تنسيقات شريط التمرير */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background-color: #4da1a9;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #3a8c94;
}
</style>
成功