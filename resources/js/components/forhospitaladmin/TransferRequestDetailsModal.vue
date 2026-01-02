<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
            dir="rtl"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:ambulance-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تفاصيل طلب النقل
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- Body -->
            <div class="p-8 space-y-8">
                <!-- بيانات الطلب الأساسية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الطلب الأساسية
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الطلب</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData?.requestNumber || `TR-${requestData?.id}` || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">اسم المريض</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData?.patient?.name || requestData?.patientName || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">من المستشفى</span>
                            <span class="font-bold text-[#2E5077]">{{ getHospitalName(requestData?.fromHospital) }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">حالة الطلب</span>
                            <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold inline-flex items-center gap-2">
                                {{ getStatusText(requestData?.status || requestData?.requestStatus) }}
                            </span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData?.createdAt || requestData?.requestDate) || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- سبب النقل -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:document-text-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        سبب النقل
                    </h3>
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <p class="text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-xl">{{ requestData?.reason || requestData?.transferReason || 'غير محدد' }}</p>
                    </div>
                </div>

                <!-- الرد (إذا وجد) -->
                <div v-if="requestData?.reply || requestData?.response" class="bg-green-50 border border-green-100 rounded-2xl p-6 space-y-4">
                    <h3 class="text-lg font-bold text-green-700 flex items-center gap-2">
                        <Icon icon="solar:chat-round-check-bold-duotone" class="w-6 h-6" />
                        الرد على الطلب
                    </h3>
                    <div class="bg-white p-4 rounded-xl shadow-sm">
                        <p class="text-green-800 font-medium leading-relaxed">{{ requestData.reply || requestData.response }}</p>
                    </div>
                    <div v-if="requestData.repliedAt || requestData.respondedAt" class="flex items-center gap-2 text-sm text-green-600">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        <span>تاريخ الرد: {{ formatDate(requestData.repliedAt || requestData.respondedAt) }}</span>
                    </div>
                </div>

                <!-- سبب الرفض (إذا وجد) -->
                <div v-if="requestData?.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6 space-y-4">
                    <h3 class="text-lg font-bold text-red-700 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب الرفض
                    </h3>
                    <div class="bg-white p-4 rounded-xl shadow-sm">
                        <p class="text-red-800 font-medium leading-relaxed">{{ requestData.rejectionReason }}</p>
                    </div>
                    <div v-if="requestData.rejectedAt" class="flex items-center gap-2 text-sm text-red-600">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        <span>تاريخ الرفض: {{ formatDate(requestData.rejectedAt) }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-8 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    requestData: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['close']);

const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString( {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return dateString;
    }
};

const getHospitalName = (hospitalData) => {
    if (!hospitalData) return 'غير محدد';
    return typeof hospitalData === 'object' ? hospitalData.name : hospitalData;
};

const getStatusClass = (status) => {
    switch (status) {
        case 'approved':
        case 'تم القبول':
        case 'تم الرد':
            return 'bg-green-100 text-green-700';
        case 'pending':
        case 'قيد المراجعة':
            return 'bg-yellow-100 text-yellow-700';
        case 'rejected':
        case 'مرفوض':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const getStatusText = (status) => {
    switch (status) {
        case 'approved':
        case 'تم القبول':
        case 'تم الرد':
            return 'تم القبول';
        case 'pending':
        case 'قيد المراجعة':
            return 'قيد المراجعة';
        case 'rejected':
        case 'مرفوض':
            return 'مرفوض';
        default:
            return status || 'غير محدد';
    }
};

const getStatusIcon = (status) => {
    if (!status) return 'solar:checklist-minimalistic-bold-duotone';
    
    const statusLower = String(status).toLowerCase();
    
    if (statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد')) {
        return 'solar:check-circle-bold';
    }
    if (statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض')) {
        return 'solar:close-circle-bold';
    }
    if (statusLower === 'pending' || statusLower.includes('مراجعة') || statusLower.includes('قيد')) {
        return 'solar:clock-circle-bold';
    }
    
    return 'solar:checklist-minimalistic-bold-duotone';
};

const getStatusIconClass = (status) => {
    if (!status) return 'text-[#4DA1A9]';
    
    const statusLower = String(status).toLowerCase();
    
    if (statusLower === 'approved' || statusLower.includes('قبول') || statusLower.includes('رد')) {
        return 'text-green-600';
    }
    if (statusLower === 'rejected' || statusLower.includes('رفض') || statusLower.includes('مرفوض')) {
        return 'text-red-600';
    }
    if (statusLower === 'pending' || statusLower.includes('مراجعة') || statusLower.includes('قيد')) {
        return 'text-yellow-600';
    }
    
    return 'text-[#4DA1A9]';
};

const closeModal = () => {
    emit('close');
};
</script>

<style scoped>
@media print {
    .fixed {
        position: relative;
    }
    
    .bg-black\/50 {
        background: white;
    }
    
    button {
        display: none;
    }
}
</style>
