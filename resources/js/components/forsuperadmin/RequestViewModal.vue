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
                    تفاصيل الشحنة رقم: {{ requestData.shipmentNumber || requestData.id || '...' }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- حالة التحميل -->
                <div v-if="isLoading" class="text-center py-10">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9] mx-auto"></div>
                    <p class="text-gray-600 mt-4">جاري تحميل تفاصيل الشحنة...</p>
                </div>

                <!-- حالة الخطأ -->
                <div v-else-if="error" class="text-center py-10">
                    <div class="text-red-600 text-4xl mb-4">⚠️</div>
                    <p class="text-red-600 font-semibold mb-4">{{ error }}</p>
                    <button 
                        @click="loadRequestDetails" 
                        class="px-6 py-2 bg-[#4DA1A9] text-white rounded-lg hover:bg-[#3a8c94] transition-colors"
                    >
                        حاول مرة أخرى
                    </button>
                </div>

                <!-- عرض البيانات -->
                <div>
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
                                <span class="text-gray-500 font-medium">الجهة الطالبة</span>
                                <span class="font-bold text-[#2E5077]">{{ requestDetails.department || requestDetails.requestingDepartment || 'غير محدد' }}</span>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">تاريخ الإنشاء</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.createdAt || requestDetails.date) }}</span>
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

                            <div v-if="requestDetails.notes" class="p-4 bg-gray-50 rounded-xl">
                                <span class="text-gray-500 font-medium block mb-2">ملاحظات الطلب</span>
                                <span class="font-medium text-gray-700">{{ requestDetails.notes }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- العناصر المشحونة -->
                    <div v-if="requestDetails.items && requestDetails.items.length > 0" class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            العناصر المشحونة ({{ requestDetails.items.length }})
                        </h3>

                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">اسم الصنف</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">النوع/الفئة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الكمية المطلوبة</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr 
                                            v-for="(item, index) in requestDetails.items" 
                                            :key="item.id || index"
                                            class="hover:bg-gray-50 transition-colors"
                                        >
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                        <Icon icon="solar:box-bold" class="w-5 h-5 text-blue-600" />
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-gray-900">{{ item.name || item.drugName }}</div>
                                                        <div v-if="item.dosage || item.strength" class="text-xs text-gray-500 mt-1">
                                                            {{ item.dosage || item.strength }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-1">
                                                    <span v-if="item.category" class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-md">
                                                        {{ item.category }}
                                                    </span>
                                                    <span v-if="item.type" class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-md">
                                                        {{ item.type }}
                                                    </span>
                                                </div>
                                            </td>
                                        
                                            <td class="px-6 py-4">
                                                <div class="text-right">
                                                    <span class="font-bold text-lg"
                                                        :class="{
                                                            'text-green-600': item.receivedQuantity >= (item.quantity || item.requestedQuantity),
                                                            'text-yellow-600': item.receivedQuantity < (item.quantity || item.requestedQuantity)
                                                        }">
                                                        {{ item.receivedQuantity || 0 }}
                                                    </span>
                                                    <span class="text-sm text-gray-500 mr-1">{{ item.unit || 'وحدة' }}</span>
                                                </div>
                                            </td>

                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- حالة عدم وجود عناصر -->
                    <div v-else class="text-center py-12 bg-white rounded-2xl border border-gray-100">
                        <Icon icon="solar:box-minimalistic-broken" class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                        <p class="text-gray-500 text-lg">لا توجد عناصر في هذه الشحنة</p>
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
import { ref, computed, watch, nextTick } from 'vue';
import { Icon } from "@iconify/vue";
import axios from 'axios';

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
            department: '',
            date: '',
            status: '', 
            items: [], 
            notes: '',
            confirmationNotes: '',
            createdAt: '',
            confirmedAt: ''
        })
    }
});

const emit = defineEmits(['close']);

const requestDetails = ref({});
const isLoading = ref(false);
const error = ref(null);

// إعداد الـ API
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:3000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (err) => Promise.reject(err)
);

// دالة لتحميل تفاصيل الشحنة
const loadRequestDetails = async () => {
    const requestId = props.requestData.id;
    
    // إذا لم يكن هناك ID، استخدم البيانات المرسلة مباشرة
    if (!requestId) {
        requestDetails.value = { ...props.requestData };
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        // محاولة جلب البيانات من API
        const response = await api.get(`/shipments/${requestId}`);
        
        if (response.data) {
            requestDetails.value = {
                id: response.data.id,
                shipmentNumber: response.data.shipmentNumber || response.data.code || `SH-${response.data.id}`,
                department: response.data.department || response.data.requestingDepartment,
                requestingDepartment: response.data.requestingDepartment || response.data.department,
                createdAt: response.data.createdAt || response.data.date || response.data.requestDate,
                date: response.data.date || response.data.requestDate,
                status: response.data.status,
                items: response.data.items || [],
               
                confirmedAt: response.data.confirmedAt
            };
        }
    } catch (apiError) {
        console.warn('فشل جلب البيانات من API، استخدام البيانات المرسلة:', apiError.message);
        
        // استخدام البيانات المرسلة من المكون الرئيسي
        requestDetails.value = { ...props.requestData };
        
        // إذا كانت البيانات فارغة، عرض خطأ
        if (!props.requestData.id && !props.requestData.shipmentNumber) {
            error.value = 'لا توجد بيانات للعرض';
        }
    } finally {
        isLoading.value = false;
    }
};

// دالة تنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return 'غير محدد';
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
        return dateString || 'غير محدد';
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
 
    if (statusLower.includes('جديد') || statusLower.includes('new')) {
        return 'bg-purple-100 text-purple-700';
    }
    return 'bg-gray-100 text-gray-700';
};

const closeModal = () => {
    isLoading.value = false;
    error.value = null;
    requestDetails.value = {};
    emit('close');
};

// مراقبة تغيير فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        // استخدام nextTick لضمان تحديث الـ DOM
        nextTick(() => {
            loadRequestDetails();
        });
    } else {
        requestDetails.value = {};
    }
});

// مراقبة تغيير البيانات
watch(() => props.requestData, (newData) => {
    if (props.isOpen && newData.id) {
        loadRequestDetails();
    }
}, { deep: true });
</script>

<style scoped>
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

/* تحسين جدول العناصر */
table {
    min-width: 800px;
}

@media (max-width: 768px) {
    .overflow-x-auto {
        margin-left: -1rem;
        margin-right: -1rem;
        width: calc(100% + 2rem);
    }
}
</style>