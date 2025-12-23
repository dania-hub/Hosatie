<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            
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
            <div class="p-8 space-y-6">
                <!-- بيانات المريض -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:card-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            رقم الطلب
                        </label>
                        <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right">
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData?.requestNumber || `TR-${requestData?.id}` || 'غير محدد' }}</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:user-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            اسم المريض
                        </label>
                        <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right">
                            <span class="font-bold text-[#2E5077]">{{ requestData?.patient?.name || requestData?.patientName || 'غير محدد' }}</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:hospital-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                            من المستشفى
                        </label>
                        <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right">
                            <span class="font-bold text-[#2E5077]">{{ getHospitalName(requestData?.fromHospital) }}</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                            <Icon :icon="getStatusIcon(requestData?.status || requestData?.requestStatus)" :class="getStatusIconClass(requestData?.status || requestData?.requestStatus)" class="w-4 h-4" />
                            حالة الطلب
                        </label>
                        <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right">
                            <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold inline-flex items-center gap-2">
                                <Icon :icon="getStatusIcon(requestData?.status || requestData?.requestStatus)" :class="getStatusIconClass(requestData?.status || requestData?.requestStatus)" class="w-4 h-4" />
                                {{ getStatusText(requestData?.status || requestData?.requestStatus) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- سبب النقل -->
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:document-text-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                        سبب النقل
                    </label>
                    <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right min-h-[100px]">
                        <p class="text-gray-700 leading-relaxed">{{ requestData?.reason || requestData?.transferReason || 'غير محدد' }}</p>
                    </div>
                </div>

                <!-- تاريخ الطلب -->
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                        تاريخ الطلب
                    </label>
                    <div class="bg-white border-2 border-gray-200 rounded-2xl px-4 py-3 text-right">
                        <span class="font-bold text-[#2E5077]">{{ formatDate(requestData?.createdAt || requestData?.requestDate) || 'غير محدد' }}</span>
                    </div>
                </div>

                <!-- الرد -->
                <div v-if="requestData?.reply || requestData?.response" class="bg-green-50 border-2 border-green-200 rounded-2xl p-6 space-y-4">
                    <h3 class="text-lg font-bold text-green-700 flex items-center gap-2">
                        <Icon icon="solar:chat-round-check-bold-duotone" class="w-5 h-5" />
                        الرد على الطلب
                    </h3>
                    <div class="bg-white/50 border border-green-100 rounded-xl p-4">
                        <p class="text-green-800 font-medium leading-relaxed">{{ requestData.reply || requestData.response }}</p>
                    </div>
                    <div v-if="requestData.repliedAt || requestData.respondedAt" class="flex items-center gap-2 text-sm text-green-700/80">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        <span>تاريخ الرد: {{ formatDate(requestData.repliedAt || requestData.respondedAt) }}</span>
                    </div>
                </div>

                <!-- سبب الرفض -->
                <div v-if="requestData?.rejectionReason" class="bg-red-50 border-2 border-red-200 rounded-2xl p-6 space-y-4">
                    <h3 class="text-lg font-bold text-red-700 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-5 h-5" />
                        سبب الرفض
                    </h3>
                    <div class="bg-white/50 border border-red-100 rounded-xl p-4">
                        <p class="text-red-800 font-medium leading-relaxed">{{ requestData.rejectionReason }}</p>
                    </div>
                    <div v-if="requestData.rejectedAt" class="flex items-center gap-2 text-sm text-red-700/80">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        <span>تاريخ الرفض: {{ formatDate(requestData.rejectedAt) }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:shadow-xl hover:-translate-y-0.5"
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
