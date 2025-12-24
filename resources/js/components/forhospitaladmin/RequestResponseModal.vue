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
                        <Icon icon="solar:chat-round-line-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    الرد على الطلب
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
                            <span class="text-gray-500 font-medium">رقم الملف</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData?.fileNumber || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">اسم المريض</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData?.patientName || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">التاريخ</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData?.createdAt || requestData?.createdDate) || 'غير محدد' }}</span>
                        </div>
                        
                        <div v-if="requestData?.patientPhone" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الهاتف</span>
                            <span class="font-bold text-[#2E5077] font-mono">{{ requestData.patientPhone }}</span>
                        </div>
                    </div>
                </div>

                <!-- معلومات الطلب -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:clipboard-check-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        معلومات الطلب
                    </h3>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
                        <div class="flex justify-between items-start border-b border-gray-50 pb-4">
                            <div>
                                <span class="text-gray-500 text-sm block mb-1">نوع الطلب</span>
                                <span class="font-bold text-[#2E5077] text-lg">{{ requestData?.requestType || 'غير محدد' }}</span>
                            </div>
                            <div class="text-left">
                                <span class="text-gray-500 text-sm block mb-1">الحالة الحالية</span>
                                <span :class="getStatusClass(requestData?.status || requestData?.requestStatus)" class="px-3 py-1 rounded-lg text-sm font-bold inline-block">
                                    {{ requestData?.status || requestData?.requestStatus || 'غير محدد' }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <span class="text-gray-500 text-sm block mb-2">المحتوى</span>
                            <p class="text-gray-700 bg-gray-50 p-4 rounded-xl leading-relaxed">{{ requestData?.content || 'غير محدد' }}</p>
                        </div>
                    </div>
                </div>

                <!-- معلومات النقل (تظهر فقط لطلبات النقل) -->
                <div v-if="requestData?.requestType === 'النقل' || requestData?.type === 'transfer'" class="bg-blue-50 border border-blue-100 rounded-2xl p-6 space-y-4">
                    <h3 class="text-lg font-bold text-blue-700 flex items-center gap-2">
                        <Icon icon="solar:hospital-bold-duotone" class="w-6 h-6" />
                        معلومات النقل
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-xl">
                            <span class="text-gray-500 text-sm block mb-1">من المستشفى</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData?.fromHospitalName || 'غير محدد' }}</span>
                        </div>
                        <div class="bg-white p-4 rounded-xl">
                            <span class="text-gray-500 text-sm block mb-1">إلى المستشفى</span>
                            <span class="font-bold text-[#4DA1A9]">{{ requestData?.toHospitalName || 'غير محدد' }}</span>
                        </div>
                    </div>
                </div>

                <!-- نموذج الرد -->
                <div v-if="!showRejectionNote" class="space-y-4 animate-in fade-in slide-in-from-top-4 duration-300">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:chat-line-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        {{ (requestData?.requestType === 'النقل' || requestData?.type === 'transfer') ? 'قبول الطلب' : 'الرد على الطلب' }}
                    </h3>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                        <label class="block mb-4">
                            <span class="font-bold text-gray-700 mb-2 block">
                                {{ (requestData?.requestType === 'النقل' || requestData?.type === 'transfer') ? 'ملاحظات (اختياري)' : 'نص الرد' }}
                            </span>
                            <textarea
                                v-model="responseText"
                                rows="4"
                                class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-[#4DA1A9] focus:ring-4 focus:ring-[#4DA1A9]/10 transition-all resize-none text-gray-700"
                                :placeholder="(requestData?.requestType === 'النقل' || requestData?.type === 'transfer') ? 'أدخل أي ملاحظات (اختياري)...' : 'أدخل ردك على الطلب هنا...'"
                                :disabled="isSubmitting"
                                :required="!(requestData?.requestType === 'النقل' || requestData?.type === 'transfer')"
                            ></textarea>
                        </label>
                        
                        <!-- حقل الملاحظات (يظهر فقط للشكاوى، وليس لطلبات النقل عند الموافقة) -->
                        <label v-if="requestData?.type !== 'transfer' && requestData?.requestType !== 'النقل'" class="block mt-4">
                            <span class="font-bold text-gray-700 mb-2 block">ملاحظات إضافية (اختياري)</span>
                            <textarea
                                v-model="additionalNotes"
                                rows="2"
                                class="w-full p-4 border border-gray-200 rounded-xl bg-white focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                                placeholder="أي ملاحظات إضافية..."
                                :disabled="isSubmitting"
                            ></textarea>
                        </label>
                    </div>
                </div>

                <!-- نموذج الرفض -->
                <div v-if="showRejectionNote" class="bg-red-50 border border-red-100 rounded-2xl p-6 animate-in fade-in slide-in-from-top-4 duration-300">
                    <h4 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب رفض الطلب
                    </h4>

                    <div class="space-y-4">
                        <div>
                            <textarea
                                v-model="rejectionNote"
                                rows="4"
                                class="w-full p-4 border-2 rounded-xl bg-white text-gray-800 transition-all resize-none focus:outline-none focus:ring-4 focus:ring-red-500/10"
                                :class="rejectionError ? 'border-red-500 focus:border-red-500' : 'border-red-200 focus:border-red-400'"
                                placeholder="يرجى توضيح سبب الرفض هنا. (مطلوب)"
                                :disabled="isSubmitting"
                                required
                            ></textarea>
                            <p v-if="rejectionError" class="text-sm text-red-600 mt-2 font-medium flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                                {{ rejectionError }}
                            </p>
                        </div>

                        <!-- حقل الملاحظات (يظهر فقط عند الرفض) -->
                        <label class="block">
                            <span class="font-bold text-gray-700 mb-2 block">ملاحظات إضافية (اختياري)</span>
                            <textarea
                                v-model="additionalNotes"
                                rows="2"
                                class="w-full p-4 border border-gray-200 rounded-xl bg-white focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                                placeholder="أي ملاحظات إضافية..."
                                :disabled="isSubmitting"
                            ></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex flex-col-reverse sm:flex-row justify-between gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                    :disabled="isLoading || isSubmitting"
                >
                    إلغاء
                </button>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                            :disabled="isLoading || isSubmitting"
                        >
                            تراجع
                        </button>
                        <button
                            @click="confirmRejection"
                            class="px-6 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon v-if="isSubmitting" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:close-circle-bold" class="w-5 h-5" />
                            {{ isSubmitting ? "جاري الرفض..." : "تأكيد الرفض" }}
                        </button>
                    </template>

                    <template v-else>
                        <button
                            @click="initiateRejection"
                            class="px-6 py-2.5 rounded-xl text-red-500 bg-red-50 border border-red-100 font-medium hover:bg-red-100 transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                            رفض الطلب
                        </button>

                        <button
                            @click="submitResponse"
                            class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isLoading || isSubmitting"
                        >
                            <Icon v-if="isSubmitting" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:check-circle-bold" class="w-5 h-5" />
                            {{ isSubmitting 
                                ? "جاري المعالجة..." 
                                : (requestData?.requestType === 'النقل' || requestData?.type === 'transfer') 
                                    ? "قبول الطلب" 
                                    : "إرسال الرد" 
                            }}
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
    const isTransferRequest = props.requestData?.requestType === 'النقل' || props.requestData?.type === 'transfer';
    
    // التحقق من الرد فقط للشكاوى (ليس لطلبات النقل)
    if (!isTransferRequest) {
        if (!responseText.value.trim()) {
            alert('يرجى كتابة الرد قبل الإرسال');
            return;
        }

        if (responseText.value.trim().length < 5) {
            alert('يرجى كتابة رد مفصل أكثر');
            return;
        }
    }

    isSubmitting.value = true;

    try {
        const responseData = {
            status: 'تم الرد',
            response: responseText.value.trim() || (isTransferRequest ? 'تم قبول طلب النقل' : ''), // إذا كان طلب نقل وليس هناك رد، نضع رسالة افتراضية
            notes: isTransferRequest ? null : additionalNotes.value.trim(), // لا توجد ملاحظات لطلبات النقل عند الموافقة
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
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>