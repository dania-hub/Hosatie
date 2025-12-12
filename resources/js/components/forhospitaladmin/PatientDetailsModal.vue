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
                        <Icon icon="solar:file-text-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تفاصيل الملف رقم: {{ patientData?.fileNumber || '...' }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <!-- حالة التحميل -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center h-64">
                <Icon icon="svg-spinners:ring-resize" class="w-12 h-12 text-[#4DA1A9] mb-4" />
                <p class="text-gray-500 font-medium">جاري تحميل التفاصيل...</p>
            </div>

            <!-- محتوى المودال -->
            <div v-else class="p-8 space-y-8">
                <!-- بيانات المريض الأساسية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات المريض
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الملف</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ patientData?.fileNumber || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">اسم المريض</span>
                            <span class="font-bold text-[#2E5077]">{{ patientData?.patientName || 'غير محدد' }}</span>
                        </div>
                        
                        <div v-if="patientData?.patientAge" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">العمر</span>
                            <span class="font-bold text-[#2E5077]">{{ patientData.patientAge }}</span>
                        </div>
                        
                        <div v-if="patientData?.patientGender" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الجنس</span>
                            <span class="font-bold text-[#2E5077]">{{ patientData.patientGender }}</span>
                        </div>
                        
                        <div v-if="patientData?.patientPhone" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الهاتف</span>
                            <span class="font-bold text-[#2E5077] font-mono">{{ patientData.patientPhone }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الإنشاء</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(patientData?.createdAt || patientData?.createdDate) || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- معلومات الطلب -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:clipboard-list-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الطلب
                    </h3>
                    
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                        <div class="flex justify-between items-start border-b border-gray-50 pb-4">
                            <div>
                                <span class="text-gray-500 text-sm block mb-1">نوع الطلب</span>
                                <span class="font-bold text-[#2E5077] text-lg">{{ patientData?.requestType || 'غير محدد' }}</span>
                            </div>
                            <div class="text-left">
                                <span class="text-gray-500 text-sm block mb-1">الحالة</span>
                                <span :class="getStatusClass(patientData?.status || patientData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold inline-block">
                                    {{ patientData?.status || patientData?.requestStatus || 'غير محدد' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-500 text-sm block mb-2">المحتوى</span>
                            <p class="text-gray-700 bg-gray-50 p-4 rounded-xl leading-relaxed">{{ patientData?.content || 'لا يوجد محتوى' }}</p>
                        </div>
                        
                        <div class="flex flex-wrap gap-4 pt-2">
                            <div v-if="patientData?.priority" class="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg">
                                <span class="text-gray-500 text-sm">الأولوية:</span>
                                <span :class="getPriorityClass(patientData.priority)" class="px-2 py-0.5 rounded text-xs font-bold">
                                    {{ patientData.priority }}
                                </span>
                            </div>
                            
                            <div v-if="patientData?.updatedAt" class="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg">
                                <span class="text-gray-500 text-sm">آخر تحديث:</span>
                                <span class="text-[#2E5077] font-medium text-sm">{{ formatDate(patientData.updatedAt) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الرد -->
                <div v-if="patientData?.response" class="bg-green-50 border border-green-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-green-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:chat-round-check-bold-duotone" class="w-6 h-6" />
                        الرد
                    </h3>
                    
                    <p class="text-green-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-green-100/50 mb-4">
                        {{ patientData.response }}
                    </p>
                    
                    <div class="flex flex-wrap gap-4 text-sm text-green-700/80">
                        <span v-if="patientData?.respondedAt" class="flex items-center gap-1">
                            <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                            {{ formatDate(patientData.respondedAt) }}
                        </span>
                        <span v-if="patientData?.respondedBy" class="flex items-center gap-1">
                            <Icon icon="solar:user-id-bold" class="w-4 h-4" />
                            {{ patientData.respondedBy }}
                        </span>
                    </div>
                </div>

                <!-- سبب الرفض -->
                <div v-if="patientData?.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب الرفض
                    </h3>
                    
                    <p class="text-red-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-red-100/50 mb-4">
                        {{ patientData.rejectionReason }}
                    </p>
                    
                    <div class="flex flex-wrap gap-4 text-sm text-red-700/80">
                        <span v-if="patientData?.rejectedAt" class="flex items-center gap-1">
                            <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                            {{ formatDate(patientData.rejectedAt) }}
                        </span>
                        <span v-if="patientData?.rejectedBy" class="flex items-center gap-1">
                            <Icon icon="solar:user-id-bold" class="w-4 h-4" />
                            {{ patientData.rejectedBy }}
                        </span>
                    </div>
                </div>

                <!-- المرفقات -->
                <div v-if="patientData?.attachments && patientData.attachments.length > 0" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:paperclip-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        المرفقات
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="(attachment, index) in patientData.attachments" :key="index"
                            class="bg-white p-4 rounded-xl border border-gray-200 flex items-center justify-between hover:border-[#4DA1A9] hover:shadow-md transition-all group">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <div class="w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center group-hover:bg-[#4DA1A9]/10 transition-colors">
                                    <Icon icon="solar:file-bold-duotone" class="w-6 h-6 text-gray-400 group-hover:text-[#4DA1A9]" />
                                </div>
                                <span class="text-gray-700 font-medium truncate">{{ attachment.name || attachment }}</span>
                            </div>
                            <button 
                                @click="downloadAttachment(attachment)"
                                class="text-gray-400 hover:text-[#4DA1A9] p-2 rounded-full hover:bg-[#4DA1A9]/10 transition-colors"
                                title="تحميل"
                            >
                                <Icon icon="solar:download-bold-duotone" class="w-6 h-6" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- الملاحظات الإضافية -->
                <div v-if="patientData?.notes" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        ملاحظات إضافية
                    </h3>
                    
                    <div class="p-6 bg-blue-50 border border-blue-100 rounded-2xl">
                        <p class="text-blue-800 leading-relaxed">{{ patientData.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-8 py-3 rounded-xl bg-[#2E5077] text-white font-bold hover:bg-[#1a3b5e] transition-all duration-200 shadow-lg shadow-[#2E5077]/20"
                    :disabled="isLoading"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Icon } from "@iconify/vue";
import axios from 'axios';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    patientData: {
        type: Object,
        default: () => ({})
    },
    isLoading: {
        type: Boolean,
        default: false
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
        case 'مقبول':
            return 'bg-green-100 text-green-700';
        case 'قيد المراجعة':
        case 'معلق':
            return 'bg-yellow-100 text-yellow-700';
        case 'مرفوض':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const getPriorityClass = (priority) => {
    switch (priority) {
        case 'عالية':
            return 'bg-red-100 text-red-700';
        case 'متوسطة':
            return 'bg-yellow-100 text-yellow-700';
        case 'منخفضة':
            return 'bg-blue-100 text-blue-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
};

const downloadAttachment = async (attachment) => {
    try {
        // إذا كان المرفق يحتوي على رابط للتحميل
        if (attachment.url) {
            window.open(attachment.url, '_blank');
        } else if (attachment.id) {
            // جلب المرفق من API
            const response = await axios.get(`/api/attachments/${attachment.id}/download`, {
                responseType: 'blob'
            });
            
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', attachment.name || 'attachment');
            document.body.appendChild(link);
            link.click();
            link.remove();
        }
    } catch (error) {
        console.error('Error downloading attachment:', error);
        alert('فشل في تحميل المرفق');
    }
};

const closeModal = () => {
    if (!props.isLoading) {
        emit('close');
    }
};
</script>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>