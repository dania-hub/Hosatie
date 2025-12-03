<script setup>
import { ref, computed } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

// 👈 تم إضافة 'confirm-dispensation' ليتحدث مع الكود الرئيسي
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

// دالة لتحويل الكمية الرقمية إلى نص الجرعة المطابق للصورة (باقي كما هو)
const formatDosage = (dosage) => {
    const numDosage = parseInt(dosage);
    if (numDosage === 1) return 'قرص واحد';
    if (numDosage === 2) return 'قرصين';
    if (numDosage > 0) return `${numDosage} قرص`;
    return dosage;
};

// حساب قائمة الأدوية التي سيتم صرفها فعلياً
const dispensedMedicationsList = computed(() => {
    if (!props.patient.medications) return [];
    return props.patient.medications.filter(med => 
        med.eligibilityStatus === 'مستحق' && med.dispensedQuantity > 0
    );
});

// حساب عدد الأدوية المؤهلة للصرف (المستحقة)
const totalItemsToConfirm = computed(() => {
    return dispensedMedicationsList.value.length;
});

// فتح مودال التأكيد
const openConfirmationModal = () => {
    if (totalItemsToConfirm.value === 0) {
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

// إلغاء التأكيد
const cancelConfirmation = () => {
    showConfirmationModal.value = false;
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="!showConfirmationModal && $emit('close')"
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
            :class="{ 'pointer-events-none': showConfirmationModal }"
        ></div>

        <div
            v-if="!showConfirmationModal"
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-[95vw] sm:max-w-[780px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10 border-b border-[#B8D7D9]"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon
                        icon="jam:write-f"
                        class="w-7 h-7 sm:w-9 sm:h-9 ml-2 text-[#2E5077]"
                    />
                    نموذج عرض حالة المريض
                </h2>

                <button
                    @click="$emit('close')"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-4 sm:space-y-6">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                        المعلومات الشخصية
                    </h3>

                    <div class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <label class="text-right font-medium text-[#2E5077] pt-2">الرقم الوطني</label>
                            <div class="relative w-full sm:max-w-xs">
                                <input
                                    readonly
                                    :value="patient.nationalIdDisplay"
                                    class="h-9 text-right rounded-2xl w-full border border-[#B8D7D9] bg-white cursor-default focus:ring-0 px-3"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <label class="text-right font-medium text-[#2E5077] pt-2">الإسم رباعي</label>
                            <div class="relative w-full">
                                <input
                                    readonly
                                    :value="patient.nameDisplay"
                                    class="h-9 text-right rounded-2xl w-81 border border-[#B8D7D9] bg-white cursor-default focus:ring-0 px-3"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-[100px_1fr] items-start gap-4">
                            <label class="text-right font-medium text-[#2E5077] pt-2">تاريخ الميلاد</label>
                            <div class="relative w-full sm:max-w-xs">
                                <input
                                    readonly
                                    :value="patient.birthDisplay"
                                    class="h-9 text-right rounded-2xl w-full border border-[#B8D7D9] bg-white cursor-default focus:ring-0 px-3"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 pt-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:pill" class="w-5 h-5 ml-2" />
                        صرف حصة دوائية
                    </h3>

                    <div class="flex justify-end gap-3">
                        <button @click="$emit('dispensation-record')" class="inline-flex items-center h-11 px-[11px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]">
                            سجل الصرف
                        </button>
                    </div>

                    <div v-if="patient.medications && patient.medications.length" class="overflow-x-auto bg-white rounded-xl shadow">
                        <table class="w-full text-right min-w-[700px]">
                            <thead class="bg-[#e0f1f1] text-black text-sm border-b border-[#a9c9c9]">
                                <tr>
                                    <th class="p-3 font-bold border-l border-[#a9c9c9]">إسم الدواء</th>
                                    <th class="p-3 font-bold border-l border-[#a9c9c9]">الجرعة</th>
                                    <th class="p-3 font-bold border-l border-[#a9c9c9]">الكمية الشهرية</th>
                                    <th class="p-3 font-bold border-l border-[#a9c9c9]">أخر إستلام</th>
                                    <th class="p-3 font-bold">حالة الإستحقاق</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(med, medIndex) in patient.medications" :key="medIndex" class="border-b border-gray-200 last:border-b-0">
                                    <td class="p-3 border-l border-gray-200">{{ med.drugName }}</td>
                                    <td class="p-3 border-l border-gray-200">{{ formatDosage(med.dosage) }}</td>
                                    <td class="p-3 border-l border-gray-200">{{ med.monthlyQuantity }}</td>
                                    <td class="p-3 border-l border-gray-200">{{ med.assignmentDate }}</td>

                                    <td class="p-1 sm:p-3 ">
                                        <div 
                                            class="flex items-center justify-start p-2 cursor-pointer hover:bg-gray-50 rounded-lg transition-colors"
                                            @click="toggleDispensingInput(medIndex)"
                                        >
                                            <Icon 
                                                icon="lucide:chevron-down"
                                                :class="med.eligibilityStatus === 'مستحق' ? 'text-green-600 w-5 h-5' : 'text-gray-500 w-5 h-5'"
                                                class="ml-2"
                                            />
                                            <span :class="{ 
                                                'text-green-600 font-bold': med.eligibilityStatus === 'مستحق',
                                                'text-red-600 font-bold': med.eligibilityStatus === 'غير متوفر',
                                                'text-gray-500 font-bold': med.eligibilityStatus === 'غير مستحق' 
                                            }">
                                                {{ med.eligibilityStatus }}
                                            </span>
                                        </div>

                                        <div v-if="dispensingStates[med.drugName] && med.eligibilityStatus === 'مستحق'" class="mt-2 w-full max-w-[150px] mx-auto p-1 bg-white border border-[#4DA1A9] rounded-lg shadow-md">
                                            <label class="text-xs font-medium block mb-1 text-gray-600">كمية الصرف:</label>
                                            <input
                                                v-model.number="med.dispensedQuantity"
                                                type="number"
                                                min="1"
                                                :max="parseInt(med.monthlyQuantity)"
                                                class="h-9 text-center rounded-lg w-full border border-[#B8D7D9] bg-white focus:ring-0 px-3"
                                                placeholder="أدخل الكمية"
                                                @click.stop />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-center text-gray-500 py-4">لا توجد جرعات دوائية مسجلة لهذا المريض.</p>
                    
                    <div v-if="totalItemsToConfirm > 0" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-700 font-medium">
                            <Icon icon="tabler:info-circle" class="w-4 h-4 inline ml-1" />
                            يوجد {{ totalItemsToConfirm }} دواء/أدوية مستحقة للصرف
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                <button 
                    @click="openConfirmationModal" 
                    class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-23 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                >
                    موافق
                </button>
                <button 
                    @click="$emit('close')" 
                    class="inline-flex items-center h-11 px-[19px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                >
                    إلغاء
                </button>
            </div>
        </div>

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
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                ></div>

                <div
                    class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[80] transform transition-all duration-300 scale-100"
                >
                    <div class="flex flex-col items-center">
                        <Icon
                            icon="tabler:alert-triangle-filled"
                            class="w-16 h-16 text-yellow-500 mb-4"
                        />
                        <p class="text-xl font-bold text-[#2E5077] mb-3">
                            تأكيد صرف الأدوية
                        </p>
                        <p class="text-base text-gray-700 mb-6">
                            هل أنت متأكد من رغبتك في صرف <span class="font-bold text-[#4DA1A9]">{{ totalItemsToConfirm }}</span> دواء/أدوية للمريض <span class="font-bold">{{ patient.nameDisplay || 'غير محدد' }}</span>؟
                        </p>
                        
                        <div v-if="patient.medications" class="w-full mb-6 max-h-40 overflow-y-auto">
                            <div 
                                v-for="(med, index) in dispensedMedicationsList" 
                                :key="index"
                                class="flex justify-between items-center p-2 mb-2 bg-gray-50 rounded-lg"
                            >
                                <span class="text-sm font-medium text-gray-700">{{ med.drugName }}</span>
                                <span class="text-sm text-[#4DA1A9] font-bold">{{ med.dispensedQuantity }} حبة</span>
                            </div>
                        </div>
                        
                        <div class="flex gap-4 justify-center w-full">
                            <button
                                @click="handleConfirmation"
                                :disabled="isSaving"
                                class="bg-[#4DA1A9] text-white font-semibold py-2 px-6 rounded-full hover:bg-[#3a8c94] transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center"
                            >
                                <Icon v-if="isSaving" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
                                {{ isSaving ? 'جاري الإرسال...' : 'تأكيد وحفظ' }}
                            </button>
                            <button
                                @click="cancelConfirmation"
                                :disabled="isSaving"
                                class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200 disabled:bg-gray-200 disabled:cursor-not-allowed"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>