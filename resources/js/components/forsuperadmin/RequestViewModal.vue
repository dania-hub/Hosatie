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

                 <!-- Loading State -->
                <div v-if="isLoading" class="text-center py-10">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#4DA1A9] mx-auto"></div>
                    <p class="text-gray-600 mt-4">جاري تحميل تفاصيل الشحنة...</p>
                </div>

                <!-- Error State -->
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
                
                <div v-else>
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
                                <span class="text-gray-500 font-medium">الجهة الطالبة</span>
                                <span class="font-bold text-[#2E5077]">{{ requestDetails.department || 'غير محدد' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.date) || 'غير محدد' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">حالة الطلب</span>
                                <span :class="statusClass" class="px-3 py-1 rounded-lg text-sm font-bold flex items-center gap-2">
                                    <Icon v-if="isInReceivingStatus" icon="solar:check-circle-bold" class="w-4 h-4" />
                                    {{ translateStatus(requestDetails.status) || 'جديد' }}
                                </span>
                            </div>
                            
                            <div v-if="isReceivedStatus && requestDetails.confirmation?.confirmedAt" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center md:col-span-2">
                                <span class="text-gray-500 font-medium">تاريخ الاستلام</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.confirmation.confirmedAt) || 'غير محدد' }}</span>
                            </div>
                            
                            <div v-if="isSentStatus && !isReceivedStatus && requestDetails.confirmation?.confirmedAt" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center md:col-span-2">
                                <span class="text-gray-500 font-medium">تاريخ الإرسال</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.confirmation.confirmedAt) || 'غير محدد' }}</span>
                            </div>

                            <div v-if="requestDetails.priority" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center md:col-span-2">
                                <span class="text-gray-500 font-medium">الأولوية</span>
                                <span :class="getPriorityClass(requestDetails.priority)" class="px-3 py-1 rounded-lg text-sm font-bold">
                                    {{ requestDetails.priority }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Rejection Reason -->
                    <div v-if="isRejectedStatus" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-red-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                            سبب الرفض
                        </h3>
                        <p v-if="hasRejectionReason" class="text-red-800 font-medium leading-relaxed">{{ requestDetails.rejectionReason }}</p>
                        <p v-else class="text-red-600 font-medium leading-relaxed italic">لم يتم تحديد سبب الرفض</p>
                        <p v-if="requestDetails.rejectedAt" class="text-red-600 text-sm mt-3 flex items-center gap-1">
                            <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                            بتاريخ: {{ formatDate(requestDetails.rejectedAt) }}
                        </p>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:box-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الأدوية المطلوبة
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
                                            <div class="font-bold text-[#2E5077] text-lg">{{ item.name }}</div>
                                        </div>
                                        <div class="flex gap-2 mt-1">
                                            <span v-if="item.dosage" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md font-medium">
                                                {{ item.dosage }}
                                            </span>
                                            <span v-if="item.type" class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-md font-medium">
                                                {{ item.type }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 md:gap-6 w-full md:w-auto justify-end flex-wrap">
                                        <!-- الكمية المطلوبة -->
                                        <div class="text-center">
                                            <span class="text-xs text-gray-400 block mb-1">مطلوب</span>
                                            <div class="font-bold text-[#4DA1A9] text-lg" v-html="getFormattedQuantity(getRequestedQuantity(item), item.unit || 'وحدة', item.units_per_box || 1)"></div>
                                        </div>
                                        
                                        <!-- الكمية المرسلة (تظهر فقط إذا كانت حالة الطلب تم الاستلام أو إذا كان هناك كمية مرسلة) -->
                                        <div v-if="isReceivedStatus || getSentQuantity(item) > 0" class="text-center pl-4 border-r border-gray-100">
                                            <span class="text-xs text-gray-400 block mb-1">مرسل</span>
                                            <div class="flex items-center gap-1">
                                                <div 
                                                    class="font-bold text-lg"
                                                    :class="getSentQuantity(item) >= getRequestedQuantity(item) ? 'text-green-600' : 'text-amber-600'"
                                                    v-html="getFormattedQuantity(getSentQuantity(item), item.unit || 'وحدة', item.units_per_box || 1)"
                                                ></div>
                                                <Icon v-if="getSentQuantity(item) >= getRequestedQuantity(item)" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
                                                <Icon v-else icon="solar:danger-circle-bold" class="w-5 h-5 text-amber-500" />
                                            </div>
                                        </div>
                                        
                                        <!-- الكمية المستلمة (تظهر فقط إذا كانت حالة الطلب تم الاستلام) -->
                                        <div v-if="isReceivedStatus" class="text-center pl-4 border-r border-gray-100">
                                            <span class="text-xs text-gray-400 block mb-1">مستلم</span>
                                            <div class="flex items-center gap-1">
                                                <div 
                                                    class="font-bold text-lg"
                                                    :class="getReceivedQuantity(item) >= getSentQuantity(item) ? 'text-green-600' : 'text-orange-600'"
                                                    v-html="getFormattedQuantity(getReceivedQuantity(item), item.unit || 'وحدة', item.units_per_box || 1)"
                                                ></div>
                                                <Icon v-if="getReceivedQuantity(item) >= getSentQuantity(item)" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
                                                <Icon v-else icon="solar:danger-circle-bold" class="w-5 h-5 text-orange-600" />
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

                    <div v-if="requestDetails.senderNotes || (requestDetails.notes && !Array.isArray(requestDetails.notes) && requestDetails.notes.trim())" class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                            <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                                رسالة المرسل
                            </h4>
                            <!-- Always show the initial message (Sender Message) -->
                             <div v-if="requestDetails.senderNotes">
                                <p class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.senderNotes }}</p>
                            </div>
                            <!-- Fallback for legacy format -->
                            <div v-else-if="requestDetails.notes && !Array.isArray(requestDetails.notes)">
                                <p class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
                            </div>
                        </div> 

                    <!-- Notes -->
                    <div v-if="requestDetails.storekeeperNotes || requestDetails.supplierNotes || (requestDetails.confirmation && requestDetails.confirmation.confirmationNotes) || (requestDetails.confirmationNotes && !requestDetails.confirmation)" class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الملاحظات
                        </h3>

                        <!-- ملاحظة Storekeeper (الملاحظة الأصلية عند الإنشاء) -->
                        <div v-if="requestDetails.storekeeperNotes" class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                            <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                                {{ requestDetails.storekeeperNotesSource === 'pharmacist' ? 'من الصيدلي' : requestDetails.storekeeperNotesSource === 'department' ? 'من مدير القسم' : 'ملاحظة الطلب' }}
                            </h4>
                            <p class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.storekeeperNotes }}</p>
                        </div>

                        <!-- ملاحظة من المورد (Supplier) -->
                        <div v-if="requestDetails.supplierNotes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                            <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                من المورد
                            </h4>
                            <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.supplierNotes }}</p>
                        </div>

                    

                        <!-- للتوافق مع الكود القديم -->
                        <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && requestDetails.confirmation?.notes && !requestDetails.confirmation?.confirmationNotes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                            <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                ملاحظة الإرسال
                            </h4>
                            <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.confirmation.notes }}</p>
                        </div>
                    </div>
                    
                    <!-- Confirmation Details -->
                    <div v-if="requestDetails.confirmation" class="bg-purple-50 border border-purple-100 rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
                            <Icon icon="solar:user-check-bold-duotone" class="w-6 h-6" />
                            تفاصيل التأكيد
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-if="requestDetails.confirmation.confirmedBy">
                                <span class="text-purple-600 text-sm block mb-1">تم التأكيد بواسطة</span>
                                <span class="font-bold text-purple-900">{{ requestDetails.confirmation.confirmedBy }}</span>
                            </div>
                            <div v-if="requestDetails.confirmation.confirmedAt">
                                <span class="text-purple-600 text-sm block mb-1">تاريخ التأكيد</span>
                                <span class="font-bold text-purple-900">{{ formatDate(requestDetails.confirmation.confirmedAt) }}</span>
                            </div>
                            <div v-if="requestDetails.confirmation.totalItemsSent" class="sm:col-span-2">
                                <span class="text-purple-600 text-sm block mb-1">إجمالي الوحدات المرسلة</span>
                                <span class="font-bold text-purple-900 text-lg">{{ requestDetails.confirmation.totalItemsSent }}</span>
                            </div>
                            
                            <!-- ملاحظة تأكيد الاستلام -->
                            <div v-if="requestDetails.confirmation?.confirmationNotes || (requestDetails.confirmationNotes && !requestDetails.confirmation)" class="sm:col-span-2 mt-4 p-4 bg-white/50 rounded-xl border border-purple-100/50">
                                <h4 class="font-bold text-purple-700 mb-2 flex items-center gap-2">
                                    <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                    ملاحظة تأكيد الاستلام
                                </h4>
                                <p class="text-purple-800 text-sm leading-relaxed">{{ requestDetails.confirmation?.confirmationNotes || requestDetails.confirmationNotes }}</p>
                            </div>
                            
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-between gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="printDetails" 
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 flex items-center gap-2"
                >
                    <Icon icon="mdi-light:printer" class="w-5 h-5" />
                    طباعة
                </button>
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
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
            storekeeperNotes: null,
            storekeeperNotesSource: null,
            supplierNotes: null,
            confirmation: null,
            confirmationNotes: null,
            confirmationNotesSource: null,
            rejectionReason: null,
            priority: null
        })
    }
});

const emit = defineEmits(['close']);

const requestDetails = ref({});
const isLoading = ref(false);
const error = ref(null);

// إعداد الـ API
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
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
        // Copy the prop data to ref
         requestDetails.value = {
            ...props.requestData,
            rejectionReason: (props.requestData.rejectionReason && typeof props.requestData.rejectionReason === 'string' && props.requestData.rejectionReason.trim() !== '') 
                ? props.requestData.rejectionReason.trim() 
                : null,
            rejectedAt: props.requestData.rejectedAt || null,
            notes: props.requestData.notes || '',
            storekeeperNotes: props.requestData.storekeeperNotes || null,
            storekeeperNotesSource: props.requestData.storekeeperNotesSource || null,
            supplierNotes: props.requestData.supplierNotes || null,
            confirmationNotes: props.requestData.confirmationNotes || (props.requestData.confirmation?.confirmationNotes) || null,
            confirmationNotesSource: props.requestData.confirmationNotesSource || null
        };
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        // محاولة جلب البيانات من API
        const response = await api.get(`/super-admin/shipments/${requestId}`);
        if (response.data) {
            const responseData = response.data.data ? response.data.data : response.data;
            
            // Processing notes to separate Sender message from Supplier/Other replies
            const notes = responseData.notes || [];
            let senderMsg = '';
            let supplierMsg = '';
            
            if (Array.isArray(notes) && notes.length > 0) {
                 // First note is assumed to be the Sender's original note
                 if (notes[0] && notes[0].message) {
                     senderMsg = notes[0].message;
                 }
                 
                 // Look for a specific Supplier note (if distinct from the first note)
                 const supNote = notes.find(n => n.by === 'supplier_admin');
                 if (supNote && supNote !== notes[0]) {
                     supplierMsg = supNote.message;
                 }
            } else if (typeof notes === 'string') {
                 senderMsg = notes;
            }

            requestDetails.value = {
                ...responseData,
                // Ensure field names match what Storekeeper component expects
                shipmentNumber: responseData.shipmentNumber || responseData.code || (responseData.id ? 'EXT-' + responseData.id : 'غير محدد'),
                department: responseData.department || responseData.requestingDepartment,
                date: responseData.date || responseData.requestDate,
                
                // Specific fields for logic
                notes: notes,
                senderNotes: senderMsg,
                supplierNotes: supplierMsg || responseData.supplierNotes,

                rejectionReason: responseData.rejection_reason || responseData.rejectionReason,
                rejectedAt: responseData.rejected_at || responseData.rejectedAt,
                
                items: responseData.items || []
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
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        // التحقق من أن التاريخ صحيح
        if (isNaN(date.getTime())) {
            return dateString; // إرجاع التاريخ الأصلي إذا كان غير صحيح
        }
        return date.toLocaleDateString( {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
    } catch {
        return dateString;
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

// دالة لاستخراج الكمية المطلوبة
const getRequestedQuantity = (item) => {
    // المطلوب: من requested_qty فقط
    const val = item.requested_qty ?? item.requestedQty ?? item.quantity ?? 0;
    return Number(val) || 0;
};

// دالة لاستخراج الكمية المرسلة
const getSentQuantity = (item) => {
    // المرسل: من approved_qty فقط
    const val = item.approved_qty ?? item.approvedQty ?? 0;
    return Number(val) || 0;
};

// دالة لاستخراج الكمية المستلمة
const getReceivedQuantity = (item) => {
    // المستلم: من fulfilled_qty فقط
    const val = item.fulfilled_qty ?? item.fulfilledQty ?? 0;
    return Number(val) || 0;
};

// دالة لتنسيق الكمية بالعبوة (كما في نموذج معالجة الشحنة)
const getFormattedQuantity = (quantity, unit = 'وحدة', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = 'عبوة';

    if (upb > 1) {
        const boxes = Math.floor(qty / upb);
        const remainder = qty % upb;
        
        if (boxes === 0 && qty > 0) return `${qty} ${unit}`;
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += `<br><span class="text-[10px] text-gray-400 font-normal">و ${remainder} ${unit}</span>`;
        }
        return display;
    }
    return `${qty} ${unit}`;
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return false;
    
    const statusLower = status.toLowerCase().trim();
    
    return (
        status.includes('تم الإرسال') || 
        status.includes('مُرسَل') || 
        status.includes('مؤكد') || 
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        status.includes('قيد الاستلام') ||
        statusLower === 'fulfilled' ||
        statusLower === 'delivered' ||
        statusLower === 'deliverd'
    );
});

// تحديد حالة الاستلام
const isReceivedStatus = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return false;
    
    const statusLower = status.toLowerCase().trim();
    
    return (
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        statusLower === 'delivered' ||
        statusLower === 'deliverd'
    );
});

// تحديد حالة الرفض
const isRejectedStatus = computed(() => {
    const status = requestDetails.value.status;
    return status && (
        status.includes('مرفوضة') ||
        status.includes('مرفوض') ||
        status === 'rejected' ||
        status.includes('ملغي')
    );
});

// التحقق من وجود سبب الرفض
const hasRejectionReason = computed(() => {
    const reason = requestDetails.value.rejectionReason;
    return reason && typeof reason === 'string' && reason.trim() !== '';
});

// دالة لترجمة حالة الطلب
const translateStatus = (status) => {
    if (!status) return 'جديد';
    
    const statusLower = status.toLowerCase().trim();
    
    // ترجمة الحالات الإنجليزية
    if (statusLower === 'approved') {
        return 'قيد الاستلام';
    }
    if (statusLower === 'fulfilled') {
        return 'قيد الاستلام';
    }
    if (statusLower === 'delivered' || statusLower === 'deliverd') {
        return 'تم الاستلام';
    }
    if (statusLower === 'pending') {
        return 'جديد';
    }
    
    // إذا كانت الحالة بالعربية بالفعل، نعيدها كما هي
    return status;
};

// تحديد حالة قيد الاستلام
const isInReceivingStatus = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return false;
    
    const statusLower = status.toLowerCase().trim();
    
    return (
        statusLower === 'approved' ||
        statusLower === 'fulfilled' ||
        status.includes('قيد الاستلام')
    );
});

// تنسيق فئة الحالة
const statusClass = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return 'bg-gray-200 text-gray-700';
    
    const statusLower = status.toLowerCase().trim();
    
    // التحقق من الحالات المترجمة
    if (statusLower === 'approved' || statusLower === 'fulfilled' || status.includes('قيد الاستلام')) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (statusLower === 'delivered' || statusLower === 'deliverd' || status.includes('تم الاستلام') || status.includes('مُستلَم') || status === 'تم الإستلام') {
        return 'bg-green-100 text-green-700';
    }
    if (statusLower === 'pending' || status.includes('جديد')) {
        return 'bg-gray-200 text-gray-700';
    }
    if (status.includes('مؤكد') || status.includes('تم الإرسال')) {
        return 'bg-blue-100 text-blue-700';
    }
    if (status.includes('قيد الانتظار') || status.includes('قيد المراجعة')) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (status.includes('ملغي') || status.includes('مرفوضة')) {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-gray-200 text-gray-700';
});


// دالة الطباعة
const printDetails = () => {
    const printWindow = window.open("", "_blank", "height=600,width=800");
    
    if (!printWindow || printWindow.closed || typeof printWindow.closed === "undefined") {
        alert("فشل عملية الطباعة. يرجى السماح بفتح النوافذ المنبثقة لهذا الموقع.");
        return;
    }

    const details = requestDetails.value;
    const itemsHtml = (details.items || []).map(item => {
        const requestedQty = getRequestedQuantity(item);
        const sentQty = getSentQuantity(item);
        const receivedQty = getReceivedQuantity(item);
        const unitsPerBox = item.units_per_box || item.unitsPerBox || 1;
        const unit = item.unit || 'وحدة';
        
        // استخدام getFormattedQuantity لعرض الكميات بالعلبة
        const formattedRequested = getFormattedQuantity(requestedQty, unit, unitsPerBox);
        const formattedSent = getFormattedQuantity(sentQty, unit, unitsPerBox);
        const formattedReceived = getFormattedQuantity(receivedQty, unit, unitsPerBox);
        
        return `
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">${item.name || 'غير محدد'}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedRequested.replace(/<br>/g, ' - ')}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSent.replace(/<br>/g, ' - ')}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedReceived.replace(/<br>/g, ' - ')}</td>
            </tr>
        `;
    }).join('');

    const printHtml = `
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <title>طباعة تفاصيل طلب التوريد</title>
            <style>
                body { font-family: 'Arial', sans-serif; direction: rtl; padding: 20px; }
                h1 { text-align: center; color: #2E5077; margin-bottom: 20px; }
                .info-section { margin-bottom: 20px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
                .info-label { font-weight: bold; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
                th { background-color: #9aced2; font-weight: bold; }
                .rejection-section { background-color: #fee; padding: 15px; border: 1px solid #fcc; margin-top: 20px; border-radius: 5px; }
                @media print {
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>تفاصيل طلب التوريد</h1>
            
            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">رقم الشحنة:</span>
                    <span>${details.shipmentNumber || details.id || 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">الجهة الطالبة:</span>
                    <span>${details.department || 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاريخ الطلب:</span>
                    <span>${formatDate(details.date) || 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">حالة الطلب:</span>
                    <span>${translateStatus(details.status) || 'جديد'}</span>
                </div>
            </div>

            ${details.rejectionReason ? `
                <div class="rejection-section">
                    <h3 style="color: #c00; margin-top: 0;">سبب الرفض:</h3>
                    <p>${details.rejectionReason}</p>
                    ${details.rejectedAt ? `<p style="font-size: 12px; color: #666;">تاريخ الرفض: ${formatDate(details.rejectedAt)}</p>` : ''}
                </div>
            ` : ''}

            <h2 style="margin-top: 30px;">الأدوية المطلوبة</h2>
            <table>
                <thead>
                    <tr>
                        <th>اسم الدواء</th>
                        <th>الكمية المطلوبة</th>
                        <th>الكمية المرسلة</th>
                        <th>الكمية المستلمة</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml || '<tr><td colspan="4" style="text-align: center;">لا توجد أدوية</td></tr>'}
                </tbody>
            </table>

            <p style="text-align: left; color: #666; font-size: 12px; margin-top: 30px;">
                تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} ${new Date().toLocaleTimeString('ar-SA')}
            </p>
        </body>
        </html>
    `;

    printWindow.document.write(printHtml);
    printWindow.document.close();
    
    printWindow.onload = () => {
        printWindow.focus();
        printWindow.print();
    };
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
