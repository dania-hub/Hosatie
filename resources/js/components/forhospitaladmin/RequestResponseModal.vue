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
                        icon="tabler:message-reply"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
                    الرد على الطلب
                </h3>

                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                    :disabled="isLoading || isSubmitting"
                    aria-label="إغلاق"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[70vh] overflow-y-auto">
              

                <!-- محتوى المودال -->
                <div >
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
                                    <span class="font-bold text-[#2E5077]">رقم الملف:</span>
                                    <span class="mr-2 text-gray-700 font-semibold">{{ requestData?.fileNumber || 'غير محدد' }}</span>
                                </p>

                                <p class="text-right flex justify-between sm:block">
                                    <span class="font-bold text-[#2E5077]">اسم المريض:</span>
                                    <span class="mr-2 text-gray-700">{{ requestData?.patientName || 'غير محدد' }}</span>
                                </p>

                                <p class="text-right flex justify-between sm:block">
                                    <span class="font-bold text-[#2E5077]">التاريخ:</span>
                                    <span class="mr-2 text-gray-700">{{ formatDate(requestData?.createdAt || requestData?.createdDate) || 'غير محدد' }}</span>
                                </p>

                                <p class="text-right flex justify-between sm:block" v-if="requestData?.patientPhone">
                                    <span class="font-bold text-[#2E5077]">رقم الهاتف:</span>
                                    <span class="mr-2 text-gray-700">{{ requestData.patientPhone }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:clipboard-check" class="w-5 h-5 ml-2" />
                            معلومات الطلب
                        </h3>

                        <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50 shadow-sm">
                            <p class="text-right mb-3 flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">نوع الطلب:</span>
                                <span class="mr-2 font-semibold">{{ requestData?.requestType || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right mb-3">
                                <span class="font-bold text-[#2E5077]">المحتوى:</span>
                                <span class="mr-2 text-gray-700">
                                    {{ requestData?.content || 'غير محدد' }}
                                </span>
                            </p>
                            
                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">الحالة الحالية:</span>
                                <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)"
                                    class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ requestData?.status || requestData?.requestStatus || 'غير محدد' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div v-if="!showRejectionNote" class="space-y-4">
                        <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                            <h3
                                class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                            >
                                <Icon icon="tabler:message-circle" class="w-5 h-5 ml-2" />
                                الرد على الطلب
                            </h3>

                            <label class="block text-right mb-4">
                                <span class="font-medium text-[#2E5077] mb-2 block">الرد:</span>
                                <textarea
                                    v-model="responseText"
                                    rows="4"
                                    class="w-full p-3 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                    placeholder="أدخل ردك على الطلب هنا..."
                                    :disabled="isSubmitting"
                                    required
                                ></textarea>
                            </label>

                           
                        </div>
                    </div>

                    <div
                        v-if="showRejectionNote"
                        class="mt-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg transition-all duration-300"
                    >
                        <h4 class="text-lg font-bold text-red-700 dark:text-red-400 mb-3 flex items-center">
                            <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                            سبب رفض الطلب
                        </h4>

                        <textarea
                            v-model="rejectionNote"
                            rows="4"
                            class="w-full p-3 border-2 rounded-lg text-sm transition-colors mb-2"
                            :class="rejectionError ? 'border-red-500 focus:border-red-600 focus:ring-red-600/30' : 'border-[#B8D7D9] focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/30'"
                            placeholder="يرجى توضيح سبب الرفض هنا. (مطلوب)"
                            :disabled="isSubmitting"
                            required
                        ></textarea>
                        
                        <p v-if="rejectionError" class="text-sm text-red-500 mt-1 text-right">
                            {{ rejectionError }}
                        </p>

                        <label class="block text-right mt-4">
                            <span class="font-medium text-[#2E5077] mb-2 block">ملاحظات إضافية (اختياري):</span>
                            <textarea
                                v-model="additionalNotes"
                                rows="2"
                                class="w-full p-3 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                placeholder="أي ملاحظات إضافية..."
                                :disabled="isSubmitting"
                            ></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div
                class="p-5 sm:px-6 sm:py-4 flex flex-col-reverse sm:flex-row justify-between gap-3 sticky bottom-0 bg-[#F6F4F0] dark:bg-gray-800 rounded-b-xl border-t border-gray-200 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                    :disabled="isLoading || isSubmitting"
                >
                    إلغاء
                </button>

                <div class="flex gap-3 w-full sm:w-auto">
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                            :disabled="isLoading || isSubmitting"
                        >
                            تراجع
                        </button>
                        <button
                            @click="confirmRejection"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#dc3545] hover:bg-[#c82333]"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon
                                v-if="isSubmitting"
                                icon="eos-icons:loading"
                                class="w-5 h-5 ml-2 animate-spin"
                            />
                            <Icon v-else icon="tabler:x" class="w-5 h-5 ml-2" />
                            {{ isSubmitting ? "جاري الرفض..." : "تأكيد الرفض" }}
                        </button>
                    </template>

                    <template v-else>
                        <button
                            @click="initiateRejection"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#dc3545] hover:bg-[#c82333]"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon icon="tabler:x" class="w-5 h-5 ml-2" />
                            رفض الطلب
                        </button>

                        <button
                            @click="submitResponse"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:bg-[#3a8c94]"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon
                                v-if="isSubmitting"
                                icon="eos-icons:loading"
                                class="w-5 h-5 ml-2 animate-spin"
                            />
                            <Icon v-else icon="tabler:check" class="w-5 h-5 ml-2" />
                            {{ isSubmitting ? "جاري الإرسال..." : "إرسال الرد" }}
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    requestData: {
        type: Object,
        default: () => ({})
    },
    isLoading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'submit', 'reject']);

// البيانات
const responseText = ref('');
const rejectionNote = ref('');
const additionalNotes = ref('');
const isSubmitting = ref(false);
const showRejectionNote = ref(false);
const rejectionError = ref('');

// إعادة تعيين الحقول عند فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        responseText.value = '';
        rejectionNote.value = '';
        additionalNotes.value = '';
        isSubmitting.value = false;
        showRejectionNote.value = false;
        rejectionError.value = '';
    }
});

// دوال مساعدة
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
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

// بدء عملية الرفض (يظهر حقل سبب الرفض)
const initiateRejection = () => {
    showRejectionNote.value = true;
    rejectionNote.value = '';
    rejectionError.value = '';
};

// إلغاء عملية الرفض (يعود إلى وضع القبول/الإرسال العادي)
const cancelRejection = () => {
    showRejectionNote.value = false;
    rejectionNote.value = '';
    rejectionError.value = '';
};

// تأكيد الرفض (إرسال الرفض الفعلي)
const confirmRejection = () => {
    // التحقق من حقل سبب الرفض
    if (!rejectionNote.value.trim()) {
        rejectionError.value = 'سبب الرفض مطلوب لإتمام عملية الرفض.';
        return;
    }

    if (rejectionNote.value.trim().length < 10) {
        rejectionError.value = 'يرجى كتابة سبب الرفض بتفصيل أكثر (10 أحرف على الأقل).';
        return;
    }

    isSubmitting.value = true;

    const responseData = {
        status: 'مرفوض',
        response: responseText.value.trim(),
        rejectionReason: rejectionNote.value.trim(),
        notes: additionalNotes.value.trim(),
        date: new Date().toISOString(),
        requestDetails: {
            id: props.requestData?.id,
            fileNumber: props.requestData?.fileNumber,
            patientName: props.requestData?.patientName,
            requestType: props.requestData?.requestType,
            content: props.requestData?.content
        }
    };

    emit('reject', responseData);
};

// إرسال الرد (قبول)
const submitResponse = async () => {
    if (!responseText.value.trim()) {
        alert('يرجى كتابة الرد قبل الإرسال');
        return;
    }

    if (responseText.value.trim().length < 5) {
        alert('يرجى كتابة رد مفصل أكثر');
        return;
    }

    isSubmitting.value = true;

    try {
        const responseData = {
            status: 'تم الرد',
            response: responseText.value.trim(),
            notes: additionalNotes.value.trim(),
            date: new Date().toISOString(),
            requestDetails: {
                id: props.requestData?.id,
                fileNumber: props.requestData?.fileNumber,
                patientName: props.requestData?.patientName,
                requestType: props.requestData?.requestType,
                content: props.requestData?.content
            }
        };

        emit('submit', responseData);
    } catch (error) {
        console.error("Error preparing response data:", error);
        alert("حدث خطأ أثناء تحضير بيانات الرد.");
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    if (!props.isLoading && !isSubmitting.value) {
        if (showRejectionNote.value) {
            cancelRejection();
        }
        emit('close');
    }
};
</script>

<style scoped>
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[70vh\] {
    max-height: 70vh;
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