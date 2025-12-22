<script setup>
import { Icon } from "@iconify/vue";
import { ref, computed } from "vue";

const props = defineProps({
    isOpen: Boolean,
    patient: Object,
    dispensationHistory: Array
});

const emit = defineEmits(['close']);

// حالة التحميل
const isLoading = ref(false);
const errorMessage = ref("");

// معالجة البيانات الواردة
const processedHistory = computed(() => {
    if (!props.dispensationHistory || props.dispensationHistory.length === 0) {
        return [];
    }
    
    return props.dispensationHistory.map(item => ({
        drugName: item.drugName || item.medicationName || 'غير معروف',
        quantity: item.quantity || item.quantity_dispensed || item.amount || 0,
        date: item.dispensedAt || item.date || item.dispensationDate || null,
        assignedBy: item.pharmacist || item.assignedBy || item.dispenser || 'غير معروف',
        pharmacy: item.pharmacy || null,
        notes: item.notes || ''
    }));
});

// دالة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    
    // إذا كان التاريخ بالفعل بصيغة Y/m/d، نعيده كما هو
    if (typeof dateString === 'string' && /^\d{4}\/\d{2}\/\d{2}$/.test(dateString)) {
        return dateString;
    }
    
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'غير محدد';
        
        // تنسيق التاريخ بصيغة Y/m/d
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}/${month}/${day}`;
    } catch {
        return 'غير محدد';
    }
};

</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden transform transition-all duration-300 rtl flex flex-col"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20 shrink-0">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:history-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    سجل الصرف - {{ patient?.name || patient?.fullName || 'مريض' }}
                </h2>
                <button @click="$emit('close')" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-6 overflow-y-auto grow">
                <!-- معلومات المريض -->
                <div v-if="patient" class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <span class="text-gray-500 font-medium">رقم الملف</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ patient.fileNumber || patient.id || 'غير محدد' }}</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <span class="text-gray-500 font-medium">الرقم الوطني</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ patient.nationalId || patient.nationalNumber || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- حالة التحميل -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center py-12">
                    <Icon icon="svg-spinners:ring-resize" class="w-12 h-12 text-[#4DA1A9] mb-4" />
                    <p class="text-gray-500 font-medium">جاري تحميل سجل الصرف...</p>
                </div>

                <!-- حالة الخطأ -->
                <div v-else-if="errorMessage" class="bg-red-50 border border-red-100 rounded-2xl p-6 flex items-center gap-3">
                    <Icon icon="solar:danger-circle-bold-duotone" class="w-8 h-8 text-red-500" />
                    <p class="text-red-700 font-medium">{{ errorMessage }}</p>
                </div>

                <!-- الجدول -->
                <div v-else-if="processedHistory.length > 0" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-right">
                            <thead class="bg-gray-50 text-gray-500 text-sm font-bold">
                                <tr>
                                    <th class="p-4 border-b border-gray-100">#</th>
                                    <th class="p-4 border-b border-gray-100">إسم الدواء</th>
                                    <th class="p-4 border-b border-gray-100">الكمية</th>
                                    <th class="p-4 border-b border-gray-100">تاريخ الصرف</th>
                                    <th class="p-4 border-b border-gray-100">بواسطة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(item, index) in processedHistory" 
                                    :key="index" 
                                    class="hover:bg-gray-50/50 transition-colors">
                                    <td class="p-4 text-gray-400 font-mono">{{ index + 1 }}</td>
                                    <td class="p-4 font-bold text-[#2E5077]">{{ item.drugName }}</td>
                                    <td class="p-4">
                                        <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded-lg font-bold text-sm">
                                            {{ item.quantity }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ formatDate(item.date) }}</td>
                                    <td class="p-4 text-gray-600">{{ item.assignedBy }}</td>
                                    
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- ملخص -->
                    <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-center">
                        <p class="text-sm text-gray-600 font-medium flex items-center gap-2">
                            <Icon icon="solar:bill-list-bold" class="w-5 h-5 text-[#4DA1A9]" />
                            إجمالي عدد عمليات الصرف: <span class="font-bold text-[#2E5077] text-lg">{{ processedHistory.length }}</span>
                        </p>
                    </div>
                </div>
                
                <!-- حالة لا يوجد بيانات -->
                <div v-else class="text-center py-16 bg-white rounded-2xl border border-gray-100 border-dashed">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Icon icon="solar:file-remove-bold-duotone" class="w-10 h-10 text-gray-300" />
                    </div>
                    <p class="text-gray-500 font-bold text-lg">لا يوجد سجل صرف لهذا المريض</p>
                    <p class="text-gray-400 text-sm mt-1">سيظهر هنا سجل جميع عمليات الصرف المسجلة</p>
                </div>
            </div>

            <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-between items-center shrink-0">
                <div class="text-sm text-gray-500 font-medium flex items-center gap-2">
                    <Icon icon="solar:clock-circle-bold" class="w-4 h-4" />
                    <span v-if="processedHistory.length > 0">
                        آخر صرف: {{ formatDate(processedHistory[0]?.date) }}
                    </span>
                    <span v-else>لا يوجد عمليات سابقة</span>
                </div>
                
                <div class="flex gap-3">
                  
                    
                    <button
                        @click="$emit('close')"
                        class="px-6 py-2.5 rounded-xl bg-[#2E5077] text-white font-medium hover:bg-[#1a3b5e] transition-all duration-200 shadow-lg shadow-[#2E5077]/20"
                    >
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* تنسيقات الطباعة */
@media print {
    .relative {
        position: static;
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: none;
        box-shadow: none;
        margin: 0;
        padding: 0;
        overflow: visible;
    }
    
    .fixed {
        position: static;
        padding: 0;
    }
    
    .absolute {
        display: none;
    }
    
    button {
        display: none !important;
    }

    .overflow-y-auto {
        overflow: visible !important;
        height: auto !important;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
}
</style>