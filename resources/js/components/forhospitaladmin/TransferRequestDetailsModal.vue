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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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

            <div class="p-8 space-y-8">
                <!-- بيانات المريض -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات المريض
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الطلب</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData?.requestNumber || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">اسم المريض</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData?.patientName || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">العمر</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData?.patientAge || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الرقم الوطني</span>
                            <span class="font-bold text-[#2E5077] font-mono">{{ requestData?.patientNationalId || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- معلومات طلب النقل -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:clipboard-list-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات طلب النقل
                    </h3>
                    
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-b border-gray-50 pb-6">
                            <div>
                                <span class="text-gray-500 text-sm block mb-1">من المستشفى</span>
                                <span class="font-bold text-[#2E5077] text-lg">{{ requestData?.fromHospital || 'غير محدد' }}</span>
                            </div>
                            
                            <div>
                                <span class="text-gray-500 text-sm block mb-1">حالة الطلب</span>
                                <span :class="getStatusClass(requestData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold inline-block">
                                    {{ requestData?.requestStatus || 'غير محدد' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-500 text-sm block mb-2">سبب النقل</span>
                            <p class="text-gray-700 bg-gray-50 p-4 rounded-xl leading-relaxed">{{ requestData?.transferReason || 'غير محدد' }}</p>
                        </div>
                        
                        <div class="flex items-center gap-2 pt-2">
                            <Icon icon="solar:calendar-date-bold" class="w-5 h-5 text-gray-400" />
                            <span class="text-gray-500 text-sm">تاريخ الطلب:</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData?.requestDate) || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- الرد -->
                <div v-if="requestData?.response" class="bg-green-50 border border-green-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-green-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:chat-round-check-bold-duotone" class="w-6 h-6" />
                        الرد على الطلب
                    </h3>
                    
                    <p class="text-green-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-green-100/50 mb-4">
                        {{ requestData.response }}
                    </p>
                    
                    <div class="flex flex-wrap gap-4 text-sm text-green-700/80 border-t border-green-200/50 pt-4">
                        <span v-if="requestData.respondedAt" class="flex items-center gap-1">
                            <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                            تاريخ الرد: {{ formatDate(requestData.respondedAt) }}
                        </span>
                        <span v-if="requestData.respondedBy" class="flex items-center gap-1">
                            <Icon icon="solar:user-id-bold" class="w-4 h-4" />
                            بواسطة: {{ requestData.respondedBy }}
                        </span>
                    </div>
                </div>

                <!-- سبب الرفض -->
                <div v-if="requestData?.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب الرفض
                    </h3>
                    
                    <p class="text-red-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-red-100/50 mb-2">
                        {{ requestData.rejectionReason }}
                    </p>
                    
                    <p v-if="requestData.rejectedAt" class="text-red-700/80 text-sm flex items-center gap-1">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        بتاريخ: {{ formatDate(requestData.rejectedAt) }}
                    </p>
                </div>

                <!-- المرفقات -->
                <div v-if="requestData?.attachments?.length > 0" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:paperclip-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المرفقات
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="(attachment, index) in requestData.attachments" :key="index"
                            class="bg-white p-4 rounded-xl border border-gray-200 flex items-center justify-between hover:border-[#4DA1A9] hover:shadow-md transition-all group">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <div class="w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center group-hover:bg-[#4DA1A9]/10 transition-colors">
                                    <Icon icon="solar:file-bold-duotone" class="w-6 h-6 text-gray-400 group-hover:text-[#4DA1A9]" />
                                </div>
                                <span class="text-gray-700 font-medium truncate">{{ attachment }}</span>
                            </div>
                            <button class="text-gray-400 hover:text-[#4DA1A9] p-2 rounded-full hover:bg-[#4DA1A9]/10 transition-colors">
                                <Icon icon="solar:download-bold-duotone" class="w-6 h-6" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-8 py-3 rounded-xl bg-[#2E5077] text-white font-bold hover:bg-[#1a3b5e] transition-all duration-200 shadow-lg shadow-[#2E5077]/20"
                >
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
        return date.toLocaleDateString('ar-SA', {
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

const getStatusClass = (status) => {
    switch (status) {
        case 'تم الرد':
            return 'bg-green-100 text-green-700';
        case 'قيد المراجعة':
            return 'bg-yellow-100 text-yellow-700';
        case 'مرفوض':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
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