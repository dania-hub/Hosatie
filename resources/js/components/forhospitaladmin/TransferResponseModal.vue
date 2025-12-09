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
                    الرد على طلب النقل
                </h3>

                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                    :disabled="isLoading"
                    aria-label="إغلاق"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                            بيانات الطلب
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">رقم الطلب:</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ requestData?.requestNumber || `TR-${requestData?.id}` || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">اسم المريض:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.patient?.name || requestData?.patientName || 'غير محدد' }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">من المستشفى:</span>
                                <span class="mr-2 text-gray-700">{{ getHospitalName(requestData?.fromHospital) }}</span>
                            </p>

                            <p class="text-right flex justify-between sm:block">
                                <span class="font-bold text-[#2E5077]">حالة الطلب:</span>
                                <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)"
                                    class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ getStatusText(requestData?.status || requestData?.requestStatus) }}
                                </span>
                            </p>
                        </div>

                        <p class="text-right mt-4 pt-4 border-t border-gray-200">
                            <span class="font-bold text-[#2E5077]">سبب النقل:</span><br>
                            <span class="mr-2 text-gray-700">{{ requestData?.reason || requestData?.transferReason || 'غير محدد' }}</span>
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:message-circle" class="w-5 h-5 ml-2" />
                            رد المستشفى على الطلب
                        </h3>

                        <div v-if="!showRejectionNote">
                            <label class="block text-right mb-4">
                                <span class="block text-sm font-medium text-gray-700 mb-2">تفاصيل الموافقة:</span>
                                <textarea
                                    v-model="responseText"
                                    rows="4"
                                    class="w-full p-3 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                    placeholder="أدخل تفاصيل الموافقة على النقل (مثال: الموعد المقترح، الشروط، التعليمات، إلخ)..."
                                    :disabled="isLoading"
                                ></textarea>
                            </label>

                           
                        </div>

                        <div
                            v-if="showRejectionNote"
                            class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg transition-all duration-300"
                        >
                            <h4 class="text-lg font-bold text-red-700 dark:text-red-400 mb-3 flex items-center">
                                <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                                سبب رفض طلب النقل
                            </h4>

                            <textarea
                                v-model="rejectionReason"
                                rows="4"
                                class="w-full p-3 border-2 rounded-lg text-sm transition-colors"
                                :class="rejectionError ? 'border-red-500 focus:border-red-600 focus:ring-red-600/30' : 'border-[#B8D7D9] focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/30'"
                                placeholder="يرجى توضيح سبب رفض طلب النقل هنا. (مطلوب)"
                                :disabled="isLoading"
                            ></textarea>
                            <p v-if="rejectionError" class="text-sm text-red-500 mt-1 text-right">
                                سبب الرفض مطلوب لإتمام عملية الرفض.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="p-5 sm:px-6 sm:py-4 flex flex-col-reverse sm:flex-row justify-between gap-3 sticky bottom-0 bg-[#F6F4F0] dark:bg-gray-800 rounded-b-xl border-t border-gray-200 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                    :disabled="isLoading"
                >
                    إلغاء
                </button>

                <div class="flex gap-3 w-full sm:w-auto">
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                            :disabled="isLoading"
                        >
                            تراجع
                        </button>
                        <button
                            @click="handleRejectRequest"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#dc3545] hover:bg-[#c82333]"
                            :disabled="isLoading"
                        >
                            <Icon
                                v-if="isLoading"
                                icon="eos-icons:loading"
                                class="w-5 h-5 ml-2 animate-spin"
                            />
                            <Icon v-else icon="tabler:x" class="w-5 h-5 ml-2" />
                            {{ isLoading ? "جاري الرفض..." : "تأكيد الرفض" }}
                        </button>
                    </template>

                    <template v-else>
                        <button
                            @click="initiateRejection"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#dc3545] hover:bg-[#c82333]"
                            :disabled="isLoading"
                        >
                            <Icon icon="tabler:x" class="w-5 h-5 ml-2" />
                            رفض الطلب
                        </button>

                        <button
                            @click="handleApproveRequest"
                            class="inline-flex items-center justify-center px-5 py-3 border-2 border-[#ffffff8d] h-12 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:bg-[#3a8c94]"
                            :disabled="isLoading"
                        >
                            <Icon
                                v-if="isLoading"
                                icon="eos-icons:loading"
                                class="w-5 h-5 ml-2 animate-spin"
                            />
                            <Icon v-else icon="tabler:check" class="w-5 h-5 ml-2" />
                            {{ isLoading ? "جاري الإرسال..." : "الموافقة على النقل" }}
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
import axios from "axios";

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

const emit = defineEmits(['close', 'submitted']);

// إعداد API
const api = axios.create({
    baseURL: 'http://localhost:3000/api', // قم بتعديل الرابط حسب الـ endpoint الخاص بك
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor للتعامل مع الأخطاء
api.interceptors.response.use(
    (response) => response.data,
    (error) => {
        console.error('API Error:', error);
        throw error;
    }
);

// البيانات
const responseText = ref('');
const rejectionReason = ref('');
const additionalNotes = ref('');
const isLoading = ref(false);
const showRejectionNote = ref(false);
const rejectionError = ref(false);

// إعادة تعيين الحقول عند فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        responseText.value = '';
        rejectionReason.value = '';
        additionalNotes.value = '';
        isLoading.value = false;
        showRejectionNote.value = false;
        rejectionError.value = false;
    }
});

// دوال مساعدة
const getHospitalName = (hospitalData) => {
    if (!hospitalData) return 'غير محدد';
    return typeof hospitalData === 'object' ? hospitalData.name : hospitalData;
};

const getStatusClass = (status) => {
    switch (status) {
        case 'approved':
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
        case 'تم الرد':
            return 'تم الرد';
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

// ==================== دوال API ====================

// 1. دالة للموافقة على الطلب
const approveRequest = async (requestId, approvalData) => {
    try {
        // استبدل هذا بـ endpoint الخاص بك
        const response = await api.put(`/transfer-requests/${requestId}/approve`, approvalData);
        return response;
    } catch (error) {
        console.error('Error approving request:', error);
        throw error;
    }
};

// 2. دالة لرفض الطلب
const rejectRequest = async (requestId, rejectionData) => {
    try {
        // استبدل هذا بـ endpoint الخاص بك
        const response = await api.put(`/transfer-requests/${requestId}/reject`, rejectionData);
        return response;
    } catch (error) {
        console.error('Error rejecting request:', error);
        throw error;
    }
};

// 3. دالة لتحديث حالة الطلب (بديل)
const updateRequestStatus = async (requestId, statusData) => {
    try {
        // إذا كنت تستخدم endpoint واحد لتحديث الحالة
        const response = await api.put(`/transfer-requests/${requestId}/status`, statusData);
        return response;
    } catch (error) {
        console.error('Error updating request status:', error);
        throw error;
    }
};

// ==================== معالجة الأحداث ====================

// بدء عملية الرفض
const initiateRejection = () => {
    showRejectionNote.value = true;
    rejectionReason.value = '';
    rejectionError.value = false;
};

// إلغاء عملية الرفض
const cancelRejection = () => {
    showRejectionNote.value = false;
    rejectionReason.value = '';
    rejectionError.value = false;
};

// معالجة الموافقة على الطلب
const handleApproveRequest = async () => {
   

    isLoading.value = true;

    try {
        const requestId = props.requestData?.id;
        if (!requestId) {
            throw new Error('رقم الطلب غير موجود');
        }

        const approvalData = {
            status: 'approved',
            response: responseText.value.trim(),
            notes: additionalNotes.value.trim(),
            respondedAt: new Date().toISOString(),
            respondedBy: 'admin' // يمكنك استبدال هذا بـ ID المستخدم الحالي
        };

        // اختر إحدى الطرق التالية:
        // الطريقة 1: استخدام endpoint خاص بالموافقة
        const response = await approveRequest(requestId, approvalData);
        
        // أو الطريقة 2: استخدام endpoint عام لتحديث الحالة
        // const response = await updateRequestStatus(requestId, approvalData);
        
        emit('submitted', {
            success: true,
            action: 'approved',
            data: response,
            requestId: requestId
        });
        
        showSuccessAlert('✅ تم الموافقة على طلب النقل بنجاح');
        closeModal();
        
    } catch (error) {
        console.error('Error in approval:', error);
        emit('submitted', {
            success: false,
            action: 'approved',
            error: error.message,
            requestId: props.requestData?.id
        });
        
        showErrorAlert(`❌ فشل في الموافقة على الطلب: ${error.response?.data?.message || error.message}`);
    } finally {
        isLoading.value = false;
    }
};

// معالجة رفض الطلب
const handleRejectRequest = async () => {
    if (!rejectionReason.value.trim()) {
        rejectionError.value = true;
        return;
    }

    isLoading.value = true;

    try {
        const requestId = props.requestData?.id;
        if (!requestId) {
            throw new Error('رقم الطلب غير موجود');
        }

        const rejectionData = {
            status: 'rejected',
            rejectionReason: rejectionReason.value.trim(),
            notes: additionalNotes.value.trim(),
            respondedAt: new Date().toISOString(),
            respondedBy: 'admin' // يمكنك استبدال هذا بـ ID المستخدم الحالي
        };

        // اختر إحدى الطرق التالية:
        // الطريقة 1: استخدام endpoint خاص بالرفض
        const response = await rejectRequest(requestId, rejectionData);
        
        // أو الطريقة 2: استخدام endpoint عام لتحديث الحالة
        // const response = await updateRequestStatus(requestId, rejectionData);
        
        emit('submitted', {
            success: true,
            action: 'rejected',
            data: response,
            requestId: requestId
        });
        
        showSuccessAlert('✅ تم رفض طلب النقل بنجاح');
        closeModal();
        
    } catch (error) {
        console.error('Error in rejection:', error);
        emit('submitted', {
            success: false,
            action: 'rejected',
            error: error.message,
            requestId: props.requestData?.id
        });
        
        showErrorAlert(`❌ فشل في رفض الطلب: ${error.response?.data?.message || error.message}`);
    } finally {
        isLoading.value = false;
    }
};

// دوال التنبيهات (يمكن استبدالها بنظام تنبيهات حقيقي)
const showSuccessAlert = (message) => {
    // هنا يمكنك استخدام مكتبة تنبيهات مثل SweetAlert2
    // أو إرسال event إلى المكون الأب
    console.log('Success:', message);
    // يمكنك إضافة: emit('show-alert', { type: 'success', message });
};

const showErrorAlert = (message) => {
    console.error('Error:', message);
    // يمكنك إضافة: emit('show-alert', { type: 'error', message });
};

const closeModal = () => {
    if (!isLoading.value) {
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