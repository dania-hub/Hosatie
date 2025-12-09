<script setup>
import { Icon } from "@iconify/vue";
import { ref, computed, onMounted } from "vue";

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
        quantity: item.quantity || item.amount || 'غير محدد',
        date: item.date || item.dispensationDate || 'غير محدد',
        assignedBy: item.assignedBy || item.dispenser || 'غير معروف',
        notes: item.notes || ''
    }));
});

// دالة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA');
    } catch {
        return dateString;
    }
};

</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="$emit('close')"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-[95vw] sm:max-w-[800px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
            dir="rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10 border-b border-[#B8D7D9]"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon icon="tabler:history" class="w-6 h-6 sm:w-8 sm:h-8 ml-2 text-[#2E5077]" />
                    سجل الصرف - {{ patient?.name || patient?.fullName || 'مريض' }}
                </h2>

                <button
                    @click="$emit('close')"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon icon="ri:close-large-fill" class="w-6 h-6 text-[#2E5077] mt-3" />
                </button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-6">
                <!-- معلومات المريض -->
                <div v-if="patient" class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="text-sm">
                            <span class="font-semibold text-[#2E5077]">رقم الملف:</span>
                            <span class="mr-2">{{ patient.fileNumber || patient.id || 'غير محدد' }}</span>
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold text-[#2E5077]">الرقم الوطني:</span>
                            <span class="mr-2">{{ patient.nationalId || patient.nationalNumber || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- حالة التحميل -->
                <div v-if="isLoading" class="flex items-center justify-center py-12">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-[#4DA1A9] mx-auto"></div>
                        <p class="mt-4 text-gray-600">جاري تحميل سجل الصرف...</p>
                    </div>
                </div>

                <!-- حالة الخطأ -->
                <div v-else-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <Icon icon="mdi:alert-circle-outline" class="w-5 h-5 text-red-500 ml-2" />
                        <p class="text-red-700">{{ errorMessage }}</p>
                    </div>
                </div>

                <!-- الجدول -->
                <div v-else-if="processedHistory.length > 0" 
                     class="overflow-x-auto bg-white rounded-xl shadow border border-gray-200">
                    <table class="table w-full text-right min-w-[600px] border-collapse">
                        <thead class="bg-[#9aced2] text-black text-sm sticky top-0">
                            <tr>
                                <th class="p-3 border border-gray-300">#</th>
                                <th class="p-3 border border-gray-300">إسم الدواء</th>
                                <th class="p-3 border border-gray-300">الكمية</th>
                                <th class="p-3 border border-gray-300">تاريخ الصرف</th>
                                <th class="p-3 border border-gray-300">بواسطة</th>
                                <th class="p-3 border border-gray-300">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in processedHistory" 
                                :key="index" 
                                class="hover:bg-gray-50 border-b border-gray-200">
                                <td class="p-3 border border-gray-300 text-center">{{ index + 1 }}</td>
                                <td class="p-3 border border-gray-300">{{ item.drugName }}</td>
                                <td class="p-3 border border-gray-300">{{ item.quantity }}</td>
                                <td class="p-3 border border-gray-300">{{ formatDate(item.date) }}</td>
                                <td class="p-3 border border-gray-300">{{ item.assignedBy }}</td>
                                <td class="p-3 border border-gray-300 text-gray-500 text-sm">
                                    {{ item.notes || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- ملخص -->
                    <div class="bg-gray-50 p-3 border-t border-gray-300">
                        <p class="text-sm text-gray-700 text-center">
                            إجمالي عدد عمليات الصرف: <span class="font-bold text-[#4DA1A9]">{{ processedHistory.length }}</span>
                        </p>
                    </div>
                </div>
                
                <!-- حالة لا يوجد بيانات -->
                <div v-else class="text-center py-12">
                    <Icon icon="mdi:file-document-outline" class="w-16 h-16 text-gray-300 mx-auto" />
                    <p class="text-gray-500 mt-4 text-lg">لا يوجد سجل صرف لهذا المريض</p>
                    <p class="text-gray-400 text-sm mt-2">سيظهر هنا سجل جميع عمليات الصرف المسجلة</p>
                </div>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-between items-center sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                <div class="text-sm text-gray-600">
                    <span v-if="processedHistory.length > 0">
                        آخر صرف: {{ formatDate(processedHistory[0]?.date) }}
                    </span>
                </div>
                
                <div class="flex gap-3">
                    <button
                        v-if="processedHistory.length > 0"
                        @click="window.print()"
                        class="inline-flex items-center h-11 px-4 border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                    >
                        <Icon icon="mdi:printer" class="w-4 h-4 ml-2" />
                        طباعة
                    </button>
                    
                    <button
                        @click="$emit('close')"
                        class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
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
    }
    
    .fixed {
        position: static;
    }
    
    .absolute {
        display: none;
    }
    
    button {
        display: none !important;
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