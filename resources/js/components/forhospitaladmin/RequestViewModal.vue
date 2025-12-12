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
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تفاصيل الشحنة رقم: {{ requestDetails.shipmentNumber || requestDetails.id || '...' }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- بيانات الشحنة الأساسية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-square-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الشحنة الأساسية
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الشحنة</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestDetails.shipmentNumber || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الإنشاء</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.createdAt) || 'غير محدد' }}</span>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الحالة</span>
                            <span :class="getStatusClass(requestDetails.status)" class="px-3 py-1 rounded-lg text-sm font-bold">
                                {{ requestDetails.status || 'غير محدد' }}
                            </span>
                        </div>
                        
                        <div v-if="requestDetails.confirmedAt" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ التأكيد</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.confirmedAt) }}</span>
                        </div>
                    </div>
                </div>

                <!-- سبب الرفض -->
                <div v-if="requestDetails.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب الرفض
                    </h3>
                    
                    <p class="text-red-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-red-100/50 mb-2">
                        {{ requestDetails.rejectionReason }}
                    </p>
                    
                    <p v-if="requestDetails.rejectedAt" class="text-red-700/80 text-sm flex items-center gap-1">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        بتاريخ: {{ formatDate(requestDetails.rejectedAt) }}
                    </p>
                </div>

                <!-- الأدوية -->
                <div v-if="requestDetails.items && requestDetails.items.length > 0" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:box-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        العناصر المشحونة
                        <span v-if="isSentStatus" class="mr-2 text-sm text-gray-400 font-normal">
                            (مطلوب / مُرسل)
                        </span>
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                            <div 
                                v-for="(item, index) in requestDetails.items" 
                                :key="index"
                                class="p-4 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex justify-between items-start gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-[#2E5077] text-lg">{{ item.name || item.drugName }}</h4>
                                            <span v-if="item.dosage || item.strength" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md font-medium">
                                                {{ item.dosage || item.strength }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex gap-2 mt-2">
                                            <span v-if="item.type" class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                {{ item.type }}
                                            </span>
                                            <span v-if="item.category" class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                {{ item.category }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="text-left flex flex-col items-end gap-2">
                                        <div class="flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                                            <span class="text-gray-500 text-sm font-medium">الكمية:</span>
                                            <span class="text-[#4DA1A9] font-bold text-lg">
                                                {{ item.quantity }} <span class="text-sm font-normal text-gray-500">{{ item.unit || 'وحدة' }}</span>
                                            </span>
                                        </div>
                                        
                                        <div v-if="item.receivedQuantity"
                                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg border"
                                            :class="{
                                                'bg-green-50 border-green-100 text-green-700': item.receivedQuantity >= item.quantity,
                                                'bg-amber-50 border-amber-100 text-amber-700': item.receivedQuantity < item.quantity
                                            }"
                                        >
                                            <span class="text-sm font-medium">مستلم:</span>
                                            <span class="font-bold">
                                                {{ item.receivedQuantity || 0 }}
                                            </span>
                                            <Icon v-if="item.receivedQuantity >= item.quantity" icon="solar:check-circle-bold" class="w-4 h-4" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الملاحظات -->
                <div v-if="requestDetails.notes || requestDetails.confirmationNotes" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الملاحظات
                    </h3>
                    
                    <div v-if="requestDetails.confirmationNotes" class="bg-green-50 border border-green-100 rounded-2xl p-5">
                        <p class="font-bold text-green-800 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            ملاحظة الإرسال:
                        </p>
                        <p class="text-green-700 leading-relaxed bg-white/50 p-3 rounded-xl border border-green-100/50">
                            {{ requestDetails.confirmationNotes }}
                        </p>
                    </div>

                    <div v-if="requestDetails.notes" class="bg-blue-50 border border-blue-100 rounded-2xl p-5">
                        <p class="font-bold text-blue-800 mb-2 flex items-center gap-2">
                            <Icon icon="solar:document-text-bold" class="w-5 h-5" />
                            ملاحظة الطلب الأصلية:
                        </p>
                        <p class="text-blue-700 leading-relaxed bg-white/50 p-3 rounded-xl border border-blue-100/50">
                            {{ requestDetails.notes }}
                        </p>
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
import { ref, computed, watch } from 'vue';
import { Icon } from "@iconify/vue";
import axios from 'axios';

// إعداد axios للمودال
const modalApi = axios.create({
  baseURL: 'http://localhost:3000/api',
  timeout: 8000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    requestData: {
        type: Object,
        default: () => ({ 
            id: null,
            shipmentNumber: '',
            createdAt: '', 
            status: '', 
            items: [], 
            notes: '',
            rejectionReason: null,
        })
    }
});

const emit = defineEmits(['close']);

const requestDetails = ref({ ...props.requestData });
const isLoading = ref(false);
const modalError = ref(null);

// دالة لجلب التفاصيل من API
const fetchRequestDetails = async () => {
    if (!props.requestData.id) {
        modalError.value = 'رقم الشحنة غير صالح';
        return;
    }

    isLoading.value = true;
    modalError.value = null;

    try {
        const response = await modalApi.get(`/shipments/${props.requestData.id}`);
        
        requestDetails.value = {
            id: response.data.id,
            shipmentNumber: response.data.shipmentNumber || response.data.code || `SH-${response.data.id}`,
            createdAt: response.data.createdAt || response.data.requestDate,
            status: response.data.status,
            items: response.data.items || [],
            notes: response.data.notes,
            rejectionReason: response.data.rejectionReason,
            confirmationNotes: response.data.confirmationNotes,
            rejectedAt: response.data.rejectedAt,
            confirmedAt: response.data.confirmedAt
        };
    } catch (err) {
        modalError.value = 'حدث خطأ في تحميل تفاصيل الشحنة';
        console.error('Error fetching shipment details:', err);
    } finally {
        isLoading.value = false;
    }
};

// Watch لتحديث البيانات عند فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal && props.requestData.id) {
        fetchRequestDetails();
    }
}, { immediate: true });

// دالة تنسيق التاريخ
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

// دالة لتنسيق فئة الحالة
const getStatusClass = (status) => {
    if (!status) return 'bg-gray-100 text-gray-700';
    
    const statusLower = status.toLowerCase();
    
    if (statusLower.includes('تم الاستلام') || statusLower.includes('delivered') || statusLower.includes('مستلم')) {
        return 'bg-green-100 text-green-700';
    }
    if (statusLower.includes('مؤكد') || statusLower.includes('confirmed') || statusLower.includes('تم الإرسال')) {
        return 'bg-blue-100 text-blue-700';
    }
    if (statusLower.includes('قيد الانتظار') || statusLower.includes('pending') || 
        statusLower.includes('قيد المراجعة') || statusLower.includes('قيد التجهيز') || 
        statusLower.includes('processing')) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (statusLower.includes('ملغي') || statusLower.includes('مرفوضة') || statusLower.includes('rejected')) {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-gray-100 text-gray-700';
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return false;
    
    const statusLower = status.toLowerCase();
    return statusLower.includes('تم الإرسال') || 
           statusLower.includes('sent') || 
           statusLower.includes('مؤكد') || 
           statusLower.includes('confirmed') || 
           statusLower.includes('تم الاستلام') ||
           statusLower.includes('delivered');
});

const closeModal = () => {
    isLoading.value = false;
    modalError.value = null;
    emit('close');
};
</script>

<style scoped>
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}
</style>