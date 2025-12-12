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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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
                        <Icon icon="solar:chat-round-line-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    الرد على طلب النقل
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- بيانات الطلب -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الطلب
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
                            <span class="text-gray-500 font-medium">الحالة</span>
                            <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold">
                                {{ getStatusText(requestData?.status || requestData?.requestStatus) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-gray-50 rounded-xl">
                        <span class="text-gray-500 font-medium block mb-2">سبب النقل</span>
                        <p class="text-gray-700 leading-relaxed">{{ requestData?.reason || requestData?.transferReason || 'غير محدد' }}</p>
                    </div>
                </div>

                <!-- نموذج الرد -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:chat-line-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        رد المستشفى
                    </h3>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <div v-if="!showRejectionNote" class="animate-in fade-in slide-in-from-top-4 duration-300">
                            <label class="block mb-4">
                                <span class="font-bold text-gray-700 mb-2 block">تفاصيل الموافقة</span>
                                <textarea
                                    v-model="responseText"
                                    rows="4"
                                    class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-[#4DA1A9] focus:ring-4 focus:ring-[#4DA1A9]/10 transition-all resize-none text-gray-700"
                                    placeholder="أدخل تفاصيل الموافقة على النقل (مثال: الموعد المقترح، الشروط، التعليمات، إلخ)..."
                                    :disabled="isLoading"
                                ></textarea>
                            </label>
                        </div>

                        <div v-if="showRejectionNote" class="bg-red-50 border border-red-100 rounded-2xl p-6 animate-in fade-in slide-in-from-top-4 duration-300">
                            <h4 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                                <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                                سبب رفض طلب النقل
                            </h4>

                            <textarea
                                v-model="rejectionReason"
                                rows="4"
                                class="w-full p-4 border-2 rounded-xl bg-white text-gray-800 transition-all resize-none focus:outline-none focus:ring-4 focus:ring-red-500/10"
                                :class="rejectionError ? 'border-red-500 focus:border-red-500' : 'border-red-200 focus:border-red-400'"
                                placeholder="يرجى توضيح سبب رفض طلب النقل هنا. (مطلوب)"
                                :disabled="isLoading"
                            ></textarea>
                            <p v-if="rejectionError" class="text-sm text-red-600 mt-2 font-medium flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                                سبب الرفض مطلوب لإتمام عملية الرفض.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex flex-col-reverse sm:flex-row justify-between gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                    :disabled="isLoading"
                >
                    إلغاء
                </button>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                            :disabled="isLoading"
                        >
                            تراجع
                        </button>
                        <button
                            @click="handleRejectRequest"
                            class="px-6 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading"
                        >
                            <Icon v-if="isLoading" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:close-circle-bold" class="w-5 h-5" />
                            {{ isLoading ? "جاري الرفض..." : "تأكيد الرفض" }}
                        </button>
                    </template>

                    <template v-else>
                        <button
                            @click="initiateRejection"
                            class="px-6 py-2.5 rounded-xl text-red-500 bg-red-50 border border-red-100 font-medium hover:bg-red-100 transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading"
                        >
                            <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                            رفض الطلب
                        </button>

                        <button
                            @click="handleApproveRequest"
                            class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading"
                        >
                            <Icon v-if="isLoading" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:check-circle-bold" class="w-5 h-5" />
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
    baseURL: 'http://localhost:3000/api',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

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

const approveRequest = async (requestId, approvalData) => {
    try {
        const response = await api.put(`/transfer-requests/${requestId}/approve`, approvalData);
        return response;
    } catch (error) {
        console.error('Error approving request:', error);
        throw error;
    }
};

const rejectRequest = async (requestId, rejectionData) => {
    try {
        const response = await api.put(`/transfer-requests/${requestId}/reject`, rejectionData);
        return response;
    } catch (error) {
        console.error('Error rejecting request:', error);
        throw error;
    }
};

// ==================== معالجة الأحداث ====================

const initiateRejection = () => {
    showRejectionNote.value = true;
    rejectionReason.value = '';
    rejectionError.value = false;
};

const cancelRejection = () => {
    showRejectionNote.value = false;
    rejectionReason.value = '';
    rejectionError.value = false;
};

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
            respondedBy: 'admin'
        };

        const response = await approveRequest(requestId, approvalData);
        
        emit('submitted', {
            success: true,
            action: 'approved',
            data: response,
            requestId: requestId
        });
        
        closeModal();
        
    } catch (error) {
        console.error('Error in approval:', error);
        emit('submitted', {
            success: false,
            action: 'approved',
            error: error.message,
            requestId: props.requestData?.id
        });
        alert(`❌ فشل في الموافقة على الطلب: ${error.response?.data?.message || error.message}`);
    } finally {
        isLoading.value = false;
    }
};

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
            respondedBy: 'admin'
        };

        const response = await rejectRequest(requestId, rejectionData);
        
        emit('submitted', {
            success: true,
            action: 'rejected',
            data: response,
            requestId: requestId
        });
        
        closeModal();
        
    } catch (error) {
        console.error('Error in rejection:', error);
        emit('submitted', {
            success: false,
            action: 'rejected',
            error: error.message,
            requestId: props.requestData?.id
        });
        alert(`❌ فشل في رفض الطلب: ${error.response?.data?.message || error.message}`);
    } finally {
        isLoading.value = false;
    }
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
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>