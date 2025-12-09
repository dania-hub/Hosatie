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
         
           

            <!-- المحتوى العادي -->
            <div >
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
                        تفاصيل الشحنة رقم : {{ requestDetails.shipmentNumber || requestDetails.id || '...' }}
                    </h3>
                
                    <button
                        @click="closeModal"
                        class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                    >
                        <Icon icon="tabler:x" class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                    <!-- بيانات الشحنة الأساسية -->
                    <div class="space-y-4 pt-1">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:info-square" class="w-5 h-5 ml-2" />
                            بيانات الشحنة الأساسية
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-white p-4 rounded-lg border border-[#B8D7D9]/50 shadow-sm">
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">رقم الشحنة:</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ requestDetails.shipmentNumber || 'غير محدد' }}</span>
                            </p>
                            
                            
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">تاريخ الإنشاء:</span>
                                <span class="mr-2 text-gray-700">{{ formatDate(requestDetails.createdAt) || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">الحالة:</span>
                                <span :class="getStatusClass(requestDetails.status)" class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ requestDetails.status || 'غير محدد' }}
                                </span>
                            </p>
                            
                            <p v-if="requestDetails.confirmedAt" class="text-right sm:col-span-2">
                                <span class="font-bold text-[#2E5077]">تاريخ التأكيد:</span>
                                <span class="mr-2 text-gray-700">
                                    {{ formatDate(requestDetails.confirmedAt) }}
                                </span>
                            </p>
                            
                          
                        </div>
                    </div>

                    <!-- سبب الرفض -->
                    <div v-if="requestDetails.rejectionReason" class="space-y-4 pt-2">
                        <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                            <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                            سبب الرفض
                        </h3>
                        
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-700 font-medium">{{ requestDetails.rejectionReason }}</p>
                            <p v-if="requestDetails.rejectedAt" class="text-red-600 text-sm mt-2">
                                بتاريخ: {{ formatDate(requestDetails.rejectedAt) }}
                            </p>
                        </div>
                    </div>

                    <!-- الأدوية -->
                    <div class="mt-8" v-if="requestDetails.items && requestDetails.items.length > 0">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:package" class="w-5 h-5 ml-2" />
                            العناصر المشحونة
                            <span v-if="isSentStatus" class="mr-2 text-sm text-gray-500 font-normal">
                                (مطلوب / مُرسل)
                            </span>
                        </h3>

                        <div class="p-4 border border-[#B8D7D9] rounded-md bg-white max-h-64 overflow-y-auto shadow-inner">
                            <ul class="list-none p-0 m-0 space-y-3">
                                <li
                                    v-for="(item, index) in requestDetails.items"
                                    :key="index"
                                    class="bg-gray-50 p-3 rounded-lg border border-gray-200"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <span class="font-extrabold text-[#2E5077] text-[15px]">{{ item.name || item.drugName }}</span>
                                            <div v-if="item.dosage || item.strength" class="text-xs text-gray-500 mt-1">
                                                الجرعة: <span class="font-medium">{{ item.dosage || item.strength }}</span>
                                            </div>
                                            <div v-if="item.type" class="text-xs text-gray-500">
                                                النوع: <span class="font-medium">{{ item.type }}</span>
                                            </div>
                                            <div v-if="item.category" class="text-xs text-gray-500">
                                                الفئة: <span class="font-medium">{{ item.category }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="text-left">
                                            <div class="mb-1">
                                                <span class="font-medium text-gray-700">الكمية:</span>
                                                <span class="text-[#4DA1A9] font-bold mr-1">
                                                    {{ item.quantity }} {{ item.unit || 'وحدة' }}
                                                </span>
                                            </div>
                                            
                                            <div v-if="item.receivedQuantity"
                                                :class="{
                                                    'text-green-600': item.receivedQuantity >= item.quantity,
                                                    'text-amber-600': item.receivedQuantity < item.quantity
                                                }"
                                                class="text-xs font-semibold flex items-center gap-1"
                                            >
                                                <span class="font-medium">مستلم:</span>
                                                <span class="font-bold">
                                                    {{ item.receivedQuantity || 0 }} {{ item.unit || 'وحدة' }}
                                                </span>
                                                <Icon v-if="item.receivedQuantity >= item.quantity" 
                                                      icon="tabler:circle-check" 
                                                      class="w-4 h-4" />
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- الملاحظات -->
                    <div v-if="requestDetails.notes || requestDetails.confirmationNotes" 
                        class="space-y-4 pt-2">
                        <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                            <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                            الملاحظات
                        </h3>
                        
                        <div v-if="requestDetails.confirmationNotes" class="p-4 border border-green-200 rounded-md bg-green-50">
                            <p class="font-bold text-green-700 mb-2 flex items-center">
                                <Icon icon="tabler:message-forward" class="w-5 h-5 ml-2" /> ملاحظة الإرسال:
                            </p>
                            <p class="pr-2 text-gray-700">{{ requestDetails.confirmationNotes }}</p>
                        </div>

                        <div v-if="requestDetails.notes" class="p-4 border border-blue-200 rounded-md bg-blue-50">
                            <p class="font-bold text-blue-700 mb-2 flex items-center">
                                <Icon icon="tabler:file-text" class="w-5 h-5 ml-2" /> ملاحظة الطلب الأصلية:
                            </p>
                            <p class="pr-2 text-gray-700">{{ requestDetails.notes }}</p>
                        </div>
                    </div>
                    
                   
                </div>
            </div>

            <div
                class="p-5 sm:px-6 sm:py-4 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700"
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
import { ref, computed, watch, onMounted } from 'vue';
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
            rejectedAt: response.data.rejectedAt
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

.max-h-\[70vh\] {
    max-height: 70vh;
}
</style>