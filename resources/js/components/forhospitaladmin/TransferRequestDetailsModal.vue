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
            <!-- العنوان -->
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-extrabold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:ambulance"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
                    تفاصيل طلب النقل
                </h3>

                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                    aria-label="إغلاق"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- المحتوى -->
            <div class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- بيانات المريض -->
                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                            بيانات المريض
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">رقم الطلب:</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ requestData?.requestNumber || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">اسم المريض:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.patientName || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">العمر:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.patientAge || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">الرقم الوطني:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.patientNationalId || 'غير محدد' }}</span>
                            </p>
                        </div>
                    </div>
                </div>

              <div class="space-y-4">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:clipboard-check" class="w-5 h-5 ml-2" />
                        معلومات طلب النقل
                    </h3>
                    

                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50 shadow-sm">
                         <p class="text-right mb-3 flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">من المستشفى :</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ requestData?.fromHospital || 'غير محدد' }}</span>
                            </p>
                        <p class="text-right mb-3 flex justify-between sm:block">
                            <span class="font-bold text-[#2E5077]">سبب النقل:</span>
                            <span class="mr-2 ">{{ requestData?.transferReason || 'غير محدد' }}</span>
                        </p>

                        
                        
                        <p class="text-right flex justify-between sm:block">
                            <span class="font-bold text-[#2E5077]">حالة الطلب:</span>
                            <span :class="getStatusClass(requestData?.requestStatus)"
                                class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                {{ requestData?.requestStatus || 'غير محدد' }}
                            </span>
                        </p>
                          <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">تاريخ الطلب:</span>
                                <span class="mr-2 text-gray-700">{{ formatDate(requestData?.requestDate) || 'غير محدد' }}</span>
                            </p>
                    </div>
                </div>

                <!-- الرد (إذا كان موجودًا) -->
                <div v-if="requestData?.response" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:message-circle" class="w-5 h-5 ml-2" />
                        الرد على الطلب
                    </h3>

                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-green-700 font-semibold mb-2">الرد:</p>
                        <p class="pr-2 text-gray-700">{{ requestData.response }}</p>

                        <div class="mt-3 pt-3 border-t border-green-200 text-sm text-gray-600">
                            <p v-if="requestData.respondedAt">
                                تاريخ الرد: {{ formatDate(requestData.respondedAt) }}
                            </p>
                            <p v-if="requestData.respondedBy">
                                تم الرد بواسطة: {{ requestData.respondedBy }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- سبب الرفض (إذا كان موجودًا) -->
                <div v-if="requestData?.rejectionReason" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                        سبب الرفض
                    </h3>

                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-700">{{ requestData.rejectionReason }}</p>
                        <p v-if="requestData.rejectedAt" class="text-red-600 text-sm mt-2">
                            بتاريخ: {{ formatDate(requestData.rejectedAt) }}
                        </p>
                    </div>
                </div>

                <!-- المرفقات (إذا كانت موجودة) -->
                <div v-if="requestData?.attachments?.length > 0" class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:paperclip" class="w-5 h-5 ml-2" />
                        المرفقات
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="(attachment, index) in requestData.attachments" :key="index"
                            class="bg-white p-3 rounded-lg border border-gray-300 flex items-center justify-between hover:shadow-md transition-shadow">
                            <span class="text-gray-700">{{ attachment }}</span>
                            <button class="text-[#4DA1A9] hover:text-[#3a8c94]">
                                <Icon icon="tabler:download" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الأزرار -->
            <div
                class="p-5 sm:px-6 sm:py-4 flex flex-col-reverse sm:flex-row justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] dark:bg-gray-800 rounded-b-xl border-t border-gray-200 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
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
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[70vh\] {
    max-height: 70vh;
}

@media print {
    .fixed {
        position: relative;
    }
    
    .bg-black\/40 {
        background: white;
    }
    
    button {
        display: none;
    }
}
</style>