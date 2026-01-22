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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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
                    تفاصيل طلب التوريد
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- حالة التحميل -->
                <div v-if="isLoading" class="text-center py-20">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9] mx-auto"></div>
                    <p class="text-gray-600 mt-4 font-medium">جاري تحميل البيانات...</p>
                </div>
                
                <!-- حالة الخطأ -->
                <div v-else-if="modalError" class="text-center py-20 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="p-4 bg-red-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <Icon icon="solar:danger-bold" class="w-10 h-10 text-red-600" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">حدث خطأ</h3>
                    <p class="text-red-600 font-medium">{{ modalError }}</p>
                    <button @click="fetchRequestDetails" class="mt-6 px-6 py-2 bg-[#2E5077] text-white rounded-xl hover:bg-[#1a3b5e] transition-colors">
                        إعادة المحاولة
                    </button>
                </div>

                <template v-else>
                    <!-- Basic Info -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                            <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            البيانات الأساسية
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">رقم الشحنة</span>
                                <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestDetails.shipmentNumber || requestDetails.id || 'غير محدد' }}</span>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.createdAt || requestDetails.date) || 'غير محدد' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">حالة الطلب</span>
                                <span :class="statusClass" class="px-3 py-1 rounded-lg text-sm font-bold">{{ requestDetails.status || 'جديد' }}</span>
                            </div>
                            
                            <div v-if="requestDetails.confirmedAt" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">تاريخ التأكيد</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.confirmedAt) }}</span>
                            </div>

                            <div v-if="requestDetails.priority" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center md:col-span-2">
                                <span class="text-gray-500 font-medium">الأولوية</span>
                                <span :class="getPriorityClass(requestDetails.priority)" class="px-3 py-1 rounded-lg text-sm font-bold">
                                    {{ requestDetails.priority }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الأدوية المطلوبة
                            <span v-if="hasAnySentOrReceived" class="text-sm font-normal text-gray-400 mr-2">
                                (مطلوب<span v-if="hasAnySent"> / مرسل</span><span v-if="hasAnyReceived"> / مستلم</span>)
                            </span>
                        </h3>

                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                            <div v-if="requestDetails.items && requestDetails.items.length > 0" class="divide-y divide-gray-50">
                                <div 
                                    v-for="(item, index) in requestDetails.items" 
                                    :key="index"
                                    class="p-4 flex flex-col md:flex-row justify-between items-center gap-4 hover:bg-gray-50/50 transition-colors"
                                >
                                    <div class="flex-1 w-full md:w-auto">
                                        <div class="flex items-center gap-2">
                                            <Icon icon="solar:pill-bold" class="w-5 h-5 text-[#4DA1A9]" />
                                            <div class="font-bold text-[#2E5077] text-lg">{{ item.name || item.drugName }}</div>
                                        </div>
                                        <div class="flex gap-2 mt-1 flex-wrap">
                                            <span v-if="item.strength || item.dosage" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-md font-medium">
                                                القوة: {{ item.strength || item.dosage }}
                                            </span>
                                            <span v-if="item.unit" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-md font-medium">
                                                الوحدة: {{ item.unit }}
                                            </span>
                                            <span v-if="item.type || item.form || item.category" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md font-medium">
                                                {{ item.type || item.form || item.category }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-6 w-full md:w-auto justify-end">
                                        <div class="text-center">
                                            <span class="text-xs text-gray-400 block mb-1">مطلوب</span>
                                            <span class="font-bold text-[#4DA1A9] text-lg" v-html="getFormattedQuantity(item.requestedQuantity || item.requested_qty || item.requested || 0, item)"></span>
                                        </div>
                                        
                                        <div v-if="hasSentQuantity(item)" class="text-center pl-4 border-r border-gray-100">
                                            <span class="text-xs text-gray-400 block mb-1">مرسل</span>
                                            <div class="flex items-center gap-1">
                                                <span 
                                                    class="font-bold text-lg"
                                                    :class="getSentQuantity(item) >= (item.requestedQuantity || item.requested_qty || item.requested || 0) ? 'text-green-600' : 'text-amber-600'"
                                                    v-html="getFormattedQuantity(getSentQuantity(item) || 0, item)"
                                                ></span>
                                                <Icon v-if="getSentQuantity(item) >= (item.requestedQuantity || item.requested_qty || item.requested || 0)" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
                                                <Icon v-else icon="solar:danger-circle-bold" class="w-5 h-5 text-amber-500" />
                                            </div>
                                        </div>
                                        
                                        <div v-if="hasReceivedQuantity(item)" class="text-center pl-4 border-r border-gray-100">
                                            <span class="text-xs text-gray-400 block mb-1">مستلم</span>
                                            <div class="flex items-center gap-1">
                                                <span 
                                                    class="font-bold text-lg"
                                                    :class="getReceivedQuantity(item) >= getSentQuantity(item) ? 'text-green-600' : 'text-amber-600'"
                                                    v-html="getFormattedQuantity(getReceivedQuantity(item) || 0, item)"
                                                ></span>
                                                <Icon v-if="getReceivedQuantity(item) >= getSentQuantity(item)" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
                                                <Icon v-else icon="solar:danger-circle-bold" class="w-5 h-5 text-amber-500" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-gray-500">
                                لا توجد أدوية في هذا الطلب.
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason -->
                    <div v-if="requestDetails.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-red-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                            سبب الرفض
                        </h3>
                        <p class="text-red-800 font-medium leading-relaxed bg-white/50 p-4 rounded-xl border border-red-100/50 mb-2">{{ requestDetails.rejectionReason }}</p>
                        <p v-if="requestDetails.rejectedAt" class="text-red-600 text-sm mt-3 flex items-center gap-1">
                            <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                            بتاريخ: {{ formatDate(requestDetails.rejectedAt) }}
                        </p>
                    </div>

                    <!-- Notes -->
                    <div v-if="requestDetails.storekeeperNotes || requestDetails.supplierNotes || requestDetails.notes || requestDetails.confirmationNotes || (requestDetails.confirmation && requestDetails.confirmation.notes)" class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الملاحظات
                        </h3>

                        <div v-if="requestDetails.storekeeperNotes" class="p-5 bg-blue-50 border border-blue-100 rounded-2xl">
                            <h4 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                                ملاحظة مسؤول المخزن:
                            </h4>
                            <p class="text-blue-700 text-sm leading-relaxed bg-white/50 p-3 rounded-xl border border-blue-100/50">{{ requestDetails.storekeeperNotes }}</p>
                        </div>

                        <div v-if="requestDetails.supplierNotes" class="p-5 bg-green-50 border border-green-100 rounded-2xl">
                            <h4 class="font-bold text-green-800 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                ملاحظة المورد:
                            </h4>
                            <p class="text-green-700 text-sm leading-relaxed bg-white/50 p-3 rounded-xl border border-green-100/50">{{ requestDetails.supplierNotes }}</p>
                        </div>

                        <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && (requestDetails.confirmationNotes || (requestDetails.confirmation && requestDetails.confirmation.notes))" class="p-5 bg-green-50 border border-green-100 rounded-2xl">
                            <h4 class="font-bold text-green-800 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                ملاحظة الإرسال
                            </h4>
                            <p class="text-green-700 text-sm leading-relaxed bg-white/50 p-3 rounded-xl border border-green-100/50">{{ requestDetails.confirmationNotes || requestDetails.confirmation.notes }}</p>
                        </div>

                        <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && requestDetails.notes" class="p-5 bg-blue-50 border border-blue-100 rounded-2xl">
                            <h4 class="font-bold text-blue-800 mb-2 flex items-center gap-2">
                                <Icon icon="solar:document-text-bold" class="w-5 h-5" />
                                ملاحظة الطلب الأصلية:
                            </h4>
                            <p class="text-blue-700 text-sm leading-relaxed bg-white/50 p-3 rounded-xl border border-blue-100/50">{{ requestDetails.notes }}</p>
                        </div>
                    </div>
                    
                    <!-- Confirmation Details -->
                    <div v-if="requestDetails.confirmationDetails || requestDetails.confirmation" class="bg-purple-50 border border-purple-100 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
                            <Icon icon="solar:user-check-bold-duotone" class="w-6 h-6" />
                            تفاصيل التأكيد
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="(requestDetails.confirmationDetails || requestDetails.confirmation)?.confirmedBy">
                                <span class="text-purple-600 text-sm block mb-1">تم التأكيد بواسطة</span>
                                <span class="font-bold text-purple-900">{{ (requestDetails.confirmationDetails || requestDetails.confirmation).confirmedBy }}</span>
                            </div>
                            <div v-if="(requestDetails.confirmationDetails || requestDetails.confirmation)?.confirmedAt">
                                <span class="text-purple-600 text-sm block mb-1">تاريخ التأكيد</span>
                                <span class="font-bold text-purple-900">{{ formatDate((requestDetails.confirmationDetails || requestDetails.confirmation).confirmedAt) }}</span>
                            </div>
                            
                            <div v-if="(requestDetails.confirmationDetails || requestDetails.confirmation)?.confirmationNotes" class="sm:col-span-2 p-4 bg-white/50 rounded-xl border border-purple-100/50">
                                <h4 class="font-bold text-purple-700 mb-2 flex items-center gap-2">
                                    <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                    ملاحظة تأكيد الاستلام
                                </h4>
                                <p class="text-purple-800 text-sm leading-relaxed">{{ (requestDetails.confirmationDetails || requestDetails.confirmation).confirmationNotes }}</p>
                            </div>
                            
                         
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-8 py-2.5 rounded-xl bg-[#2E5077] text-white font-bold hover:bg-[#1a3b5e] transition-all duration-200 shadow-lg shadow-[#2E5077]/20"
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
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// إضافة interceptor لإضافة الـ token تلقائياً
modalApi.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

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
            storekeeperNotes: null,
            supplierNotes: null,
            confirmationDetails: null,
            rejectionReason: null,
            priority: null
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
        if (props.requestData.items && props.requestData.items.length > 0) {
            requestDetails.value = { ...props.requestData };
            return;
        }
        modalError.value = 'رقم الشحنة غير صالح';
        return;
    }

    isLoading.value = true;
    modalError.value = null;

    try {
        const response = await modalApi.get(`/admin-hospital/shipments/${props.requestData.id}`);
        
        let data = response.data;
        if (data && data.data) {
            data = data.data;
        }
        
        requestDetails.value = {
            id: data.id || props.requestData.id,
            shipmentNumber: data.shipmentNumber || data.code || `EXT-${data.id || props.requestData.id}`,
            createdAt: data.createdAt || data.requestDate || props.requestData.createdAt,
            status: data.status || props.requestData.status,
            items: data.items || props.requestData.items || [],
            notes: data.notes || props.requestData.notes,
            storekeeperNotes: data.storekeeperNotes || props.requestData.storekeeperNotes,
            supplierNotes: data.supplierNotes || props.requestData.supplierNotes,
            rejectionReason: data.rejectionReason || props.requestData.rejectionReason,
            confirmationNotes: data.confirmationNotes,
            confirmationDetails: data.confirmationDetails || props.requestData.confirmationDetails,
            rejectedAt: data.rejectedAt,
            confirmedAt: data.confirmedAt,
            priority: data.priority || props.requestData.priority
        };
    } catch (err) {
        console.error('Error fetching shipment details:', err);
        if (props.requestData.items && props.requestData.items.length > 0) {
            requestDetails.value = { ...props.requestData };
        } else {
            modalError.value = 'حدث خطأ في تحميل تفاصيل الشحنة';
        }
    } finally {
        isLoading.value = false;
    }
};

// Watch لتحديث البيانات عند فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        requestDetails.value = {
            ...props.requestData,
            rejectionReason: props.requestData.rejectionReason || null,
            rejectedAt: props.requestData.rejectedAt || null,
            notes: props.requestData.notes || '',
            storekeeperNotes: props.requestData.storekeeperNotes || null,
            supplierNotes: props.requestData.supplierNotes || null
        };
        
        if (props.requestData.id) {
            fetchRequestDetails();
        }
    }
}, { immediate: true });

// Watch لتحديث البيانات عند تغيير requestData
watch(() => props.requestData, (newData) => {
    if (props.isOpen && newData) {
        requestDetails.value = { ...newData };
        if (newData.id) {
            fetchRequestDetails();
        }
    }
}, { deep: true });

const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString({
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return dateString;
    }
};

const getFormattedQuantity = (quantity, item) => {
    if (!item) return quantity;
    const unit = item.unit || 'حبة';
    const boxUnit = (item.type === 'Liquid' || item.type === 'Syrup' || unit === 'مل') ? 'عبوة' : 'علبة';
    const unitsPerBox = Number(item.units_per_box || 1);
    const qty = Number(quantity || 0);

    if (unitsPerBox > 1) {
        const boxes = Math.floor(qty / unitsPerBox);
        const remainder = qty % unitsPerBox;
        
        if (boxes === 0 && qty > 0) {
            return `${qty} ${unit}`;
        }
        
        let display = `<span>${boxes}</span> <span class="text-[10px] text-gray-400 mr-0.5">${boxUnit}</span>`;
        if (remainder > 0) {
            display += ` و <span>${remainder}</span> <span class="text-[10px] text-gray-400 mr-0.5">${unit}</span>`;
        }
        return display;
    } else {
        return `<span>${qty}</span> <span class="text-[10px] text-gray-400 mr-0.5">${unit}</span>`;
    }
};

// دالة لتنسيق فئة الأولوية
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

// دالة للتحقق من وجود كمية مرسلة
const hasSentQuantity = (item) => {
    const sentQty = getSentQuantity(item);
    return sentQty > 0;
};

// دالة لاستخراج الكمية المرسلة
const getSentQuantity = (item) => {
    if (requestDetails.value.confirmationDetails?.receivedItems) {
        const sentItem = requestDetails.value.confirmationDetails.receivedItems.find(
            si => si.id === item.id || si.drugId === item.id
        );
        if (sentItem && sentItem.sentQuantity) return sentItem.sentQuantity;
    }
    return item.approved_qty || item.approved || item.sent || 0;
};

// دالة للتحقق من وجود كمية مستلمة
const hasReceivedQuantity = (item) => {
    const receivedQty = getReceivedQuantity(item);
    return receivedQty > 0;
};

// دالة لاستخراج الكمية المستلمة
const getReceivedQuantity = (item) => {
    if (item.receivedQuantity !== null && item.receivedQuantity !== undefined) return item.receivedQuantity;
    
    if (requestDetails.value.confirmationDetails?.receivedItems) {
        const receivedItem = requestDetails.value.confirmationDetails.receivedItems.find(
            ri => ri.id === item.id || ri.drugId === item.id
        );
        if (receivedItem && receivedItem.receivedQuantity !== null && receivedItem.receivedQuantity !== undefined) {
            return receivedItem.receivedQuantity;
        }
    }
    
    return item.fulfilled_qty || item.fulfilled || item.sent || 0;
};

// التحقق من وجود أي كمية مرسلة في أي item
const hasAnySent = computed(() => {
    if (!requestDetails.value.items || requestDetails.value.items.length === 0) return false;
    return requestDetails.value.items.some(item => hasSentQuantity(item));
});

// التحقق من وجود أي كمية مستلمة في أي item
const hasAnyReceived = computed(() => {
    if (!requestDetails.value.items || requestDetails.value.items.length === 0) return false;
    return requestDetails.value.items.some(item => hasReceivedQuantity(item));
});

// التحقق من وجود أي كمية مرسلة أو مستلمة
const hasAnySentOrReceived = computed(() => {
    return hasAnySent.value || hasAnyReceived.value;
});

// تنسيق فئة الحالة
const statusClass = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return 'bg-gray-200 text-gray-700';
    
    if (status.includes('تم الاستلام') || status.includes('مُستلَم') || status === 'تم الإستلام' || status.includes('delivered')) {
        return 'bg-green-100 text-green-700';
    }
    if (status.includes('مؤكد') || status.includes('تم الإرسال') || status.includes('confirmed')) {
        return 'bg-blue-100 text-blue-700';
    }
    if (status.includes('قيد الانتظار') || status.includes('قيد المراجعة') || status.includes('قيد التجهيز') || status.includes('pending') || status.includes('processing')) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (status.includes('ملغي') || status.includes('مرفوضة') || status.includes('rejected')) {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-gray-200 text-gray-700';
});

const closeModal = () => {
    isLoading.value = false;
    modalError.value = null;
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