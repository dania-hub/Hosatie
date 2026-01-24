<script setup>
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'dispensation-record']);

// دالة لتحويل الجرعة إلى نص الجرعة المطابق للصورة
const formatDosage = (dosage) => {
    if (!dosage) return '-';
    
    // إذا كانت الجرعة نصاً جاهزاً من الـ API (يحتوي على وحدة قياس)، نعيدها كما هي
    if (typeof dosage === 'string') {
        // التحقق من وجود وحدة قياس في النص (قرص، حبة، قارورة، كبسولة، مل، ملغ، جرام، إلخ)
        // أو وجود "يومياً" مما يعني أن النص كامل من الـ API
        const hasUnit = /(قرص|حبة|قارورة|كبسولة|مل|ملغ|جرام|غرام|وحدة|يومياً|ampoule|vial|tablet|capsule|ml|mg|g|unit)/i.test(dosage);
        if (hasUnit) {
            return dosage; // إرجاع النص كما هو لأنه يحتوي على الوحدة الصحيحة
        }
    }
    
    // إذا كانت رقماً فقط أو نصاً بدون وحدة، نحاول تحويلها
    const numDosage = parseInt(dosage);
    if (isNaN(numDosage)) return dosage;
    
    // إذا كان رقم فقط بدون وحدة، نستخدم "حبة" كافتراضي (يجب ألا يحدث هذا في الوضع الطبيعي)
    if (numDosage === 1) return '1 حبة';
    if (numDosage === 2) return '2 حبة';
    if (numDosage > 0) return `${numDosage} حبة`;
    return dosage;
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
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
        <div 
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
                    ملف المريض - عرض البيانات
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
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nameDisplay || patient.name || '-' }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">الرقم الوطني</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.nationalIdDisplay || patient.nationalId || '-' }}</div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-500">تاريخ الميلاد</label>
                            <div class="font-bold text-[#2E5077] text-lg">{{ patient.birthDisplay || patient.birth || '-' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Medications Section -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الأدوية الموصوفة
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
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(med, medIndex) in patient.medications" :key="medIndex" class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 font-medium text-gray-700">{{ med.drugName || '-' }}</td>
                                    <td class="p-4 text-gray-600">
                                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-sm font-medium">
                                            {{ formatDosage(med.dosage) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ getMonthlyQuantityDisplay(med) }}</div>
                                            <div v-if="med.totalDispensedThisMonth > 0" class="text-xs space-y-0.5">
                                              
                                                <div v-if="med.remainingQuantity > 0" class="text-green-600 font-semibold">
                                                    متبقي: {{ med.remainingQuantity }} {{ med.unit || 'حبة' }}
                                                </div>
                                                <div v-else-if="med.remainingQuantity === 0 && med.monthlyQuantityNum > 0" class="text-gray-500">
                                                    تم صرف الكمية الشهرية كاملة
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignmentDate || '-' }}</td>
                                    <td class="p-4 text-gray-500 text-sm">{{ med.assignedBy || '-' }}</td>
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
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="$emit('close')" 
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2"
                >
                    <Icon icon="mingcute:close-fill" class="w-5 h-5" />
                    إغلاق
                </button>
            </div>
        </div>
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