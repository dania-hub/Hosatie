<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4"
    >
        <div
            class="relative bg-[#F6F4F0] rounded-xl shadow-3xl w-full max-w-2xl mx-auto my-10 transform transition-all duration-300 scale-100 opacity-100 dark:bg-gray-800"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
            @click.stop
        >
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-extrabold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:file-invoice"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
                    تفاصيل الملف رقم: {{ patientData?.fileNumber || '...' }}
                </h3>
            
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                    :disabled="isLoading"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- حالة التحميل -->
            <div v-if="isLoading" class="flex justify-center items-center h-64">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9]"></div>
            </div>

            <!-- محتوى المودال -->
            <div v-else class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[80vh] overflow-y-auto">
                <!-- بيانات المريض الأساسية -->
                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                            بيانات المريض
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">رقم الملف:</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ patientData?.fileNumber || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">اسم المريض:</span>
                                <span class="mr-2 text-gray-700">{{ patientData?.patientName || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right" v-if="patientData?.patientAge">
                                <span class="font-bold text-[#2E5077]">العمر:</span>
                                <span class="mr-2 text-gray-700">{{ patientData.patientAge }}</span>
                            </p>
                            
                            <p class="text-right" v-if="patientData?.patientGender">
                                <span class="font-bold text-[#2E5077]">الجنس:</span>
                                <span class="mr-2 text-gray-700">{{ patientData.patientGender }}</span>
                            </p>
                            
                            <p class="text-right" v-if="patientData?.patientPhone">
                                <span class="font-bold text-[#2E5077]">رقم الهاتف:</span>
                                <span class="mr-2 text-gray-700">{{ patientData.patientPhone }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">التاريخ:</span>
                                <span class="mr-2 text-gray-700">{{ formatDate(patientData?.createdAt || patientData?.createdDate) || 'غير محدد' }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- معلومات الطلب -->
                <div class="space-y-4">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:clipboard" class="w-5 h-5 ml-2" />
                        معلومات الطلب
                    </h3>
                    
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50 shadow-sm">
                        <p class="text-right mb-3">
                            <span class="font-bold text-[#2E5077]">نوع الطلب:</span>
                            <span class="mr-2 font-semibold">
                                {{ patientData?.requestType || 'غير محدد' }}
                            </span>
                        </p>
                        
                        <p class="text-right mb-3">
                            <span class="font-bold text-[#2E5077]">المحتوى:</span>
                            <span class="mr-2 text-gray-700">{{ patientData?.content || 'غير محدد' }}</span>
                        </p>
                        
                        <p class="text-right mb-3" v-if="patientData?.priority">
                            <span class="font-bold text-[#2E5077]">الأولوية:</span>
                            <span :class="getPriorityClass(patientData.priority)" class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                {{ patientData.priority }}
                            </span>
                        </p>
                        
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">الحالة:</span>
                            <span :class="getStatusClass(patientData?.status || patientData?.requestStatus)" class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                {{ patientData?.status || patientData?.requestStatus || 'غير محدد' }}
                            </span>
                        </p>
                        
                        <p class="text-right mt-3" v-if="patientData?.updatedAt">
                            <span class="font-bold text-[#2E5077]">آخر تحديث:</span>
                            <span class="mr-2 text-gray-700">{{ formatDate(patientData.updatedAt) }}</span>
                        </p>
                    </div>
                </div>

                <!-- الرد -->
                <div v-if="patientData?.response" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:message-circle" class="w-5 h-5 ml-2" />
                        الرد
                    </h3>
                    
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-green-700 font-semibold mb-2">الرد:</p>
                        <p class="pr-2 text-gray-700">{{ patientData.response }}</p>
                        
                        <p v-if="patientData?.respondedAt" class="text-green-600 text-sm mt-2">
                            بتاريخ: {{ formatDate(patientData.respondedAt) }}
                        </p>
                        <p v-if="patientData?.respondedBy" class="text-green-600 text-sm">
                            بواسطة: {{ patientData.respondedBy }}
                        </p>
                    </div>
                </div>

                <!-- سبب الرفض -->
                <div v-if="patientData?.rejectionReason" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                        سبب الرفض
                    </h3>
                    
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-700">{{ patientData.rejectionReason }}</p>
                        <p v-if="patientData?.rejectedAt" class="text-red-600 text-sm mt-2">
                            بتاريخ: {{ formatDate(patientData.rejectedAt) }}
                        </p>
                        <p v-if="patientData?.rejectedBy" class="text-red-600 text-sm">
                            بواسطة: {{ patientData.rejectedBy }}
                        </p>
                    </div>
                </div>

                <!-- المرفقات -->
                <div v-if="patientData?.attachments && patientData.attachments.length > 0" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:paperclip" class="w-5 h-5 ml-2" />
                        المرفقات
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="(attachment, index) in patientData.attachments" :key="index"
                            class="bg-white p-3 rounded-lg border border-gray-300 flex items-center justify-between hover:shadow-md transition-shadow">
                            <span class="text-gray-700">{{ attachment.name || attachment }}</span>
                            <button 
                                @click="downloadAttachment(attachment)"
                                class="text-[#4DA1A9] hover:text-[#3a8c94]"
                            >
                                <Icon icon="tabler:download" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- الملاحظات الإضافية -->
                <div v-if="patientData?.notes" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                        ملاحظات إضافية
                    </h3>
                    
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="pr-2 text-gray-700">{{ patientData.notes }}</p>
                    </div>
                </div>
            </div>

            <div
                class="p-5 sm:px-6 sm:py-4 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
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
            return 'bg-red-100 text-red-800';
        case 'متوسطة':
            return 'bg-yellow-100 text-yellow-800';
        case 'منخفضة':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
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
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[80vh\] {
    max-height: 80vh;
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>