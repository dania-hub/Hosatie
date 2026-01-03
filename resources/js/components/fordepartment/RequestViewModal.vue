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
                <div class="flex items-center gap-3 relative z-10">
                    <span v-if="requestDetails.confirmation" class="bg-green-500/20 text-green-100 px-3 py-1 rounded-full text-sm font-bold border border-green-500/30 flex items-center gap-1">
                        <Icon icon="solar:check-circle-bold" class="w-4 h-4" />
                        مُستلَم
                    </span>
                    <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300">
                        <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                    </button>
                </div>
            </div>

            <div class="p-8 space-y-8">
                
                <!-- Basic Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        البيانات الأساسية
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الطلب</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestDetails.id || 'غير محدد' }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.date) || 'غير محدد' }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">حالة الطلب</span>
                            <span :class="statusClass" class="px-3 py-1 rounded-lg text-sm font-bold">{{ requestDetails.status || 'جديد' }}</span>
                        </div>
                        <div v-if="requestDetails.confirmation" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الاستلام</span>
                            <span class="font-bold text-[#2E5077]">
                                {{ requestDetails.confirmation.confirmedDate || 'غير محدد' }}
                                <span v-if="requestDetails.confirmation.confirmedTime" class="text-xs text-gray-400 block mt-1">
                                    {{ requestDetails.confirmation.confirmedTime }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Items List -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الأدوية المطلوبة
                        <span v-if="isSentStatus && !isReceivedStatus" class="text-sm font-normal text-gray-400 mr-2">(مطلوب / مُرسل)</span>
                        <span v-if="isReceivedStatus" class="text-sm font-normal text-gray-400 mr-2">(مطلوب / مُرسل / مُستلم)</span>
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div v-if="requestDetails.items.length > 0" class="divide-y divide-gray-50">
                            <div 
                                v-for="(item, index) in requestDetails.items" 
                                :key="index"
                                class="p-4 flex flex-col md:flex-row justify-between items-center gap-4 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex-1 w-full md:w-auto">
                                    <div class="font-bold text-[#2E5077] text-lg">{{ item.name }}</div>
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
                                        <span class="font-bold text-[#4DA1A9] text-lg">{{ getRequestedQuantity(item) }} <span class="text-xs text-gray-500 font-normal">{{ item.unit || 'وحدة' }}</span></span>
                                    </div>
                                    
                                    <!-- الكمية المرسلة -->
                                    <div v-if="isSentStatus || isReceivedStatus" class="text-center pl-4 border-r border-gray-100">
                                        <span class="text-xs text-gray-400 block mb-1">مرسل</span>
                                        <div class="flex items-center gap-1">
                                            <span 
                                                class="font-bold text-lg"
                                                :class="getSentQuantity(item) >= getRequestedQuantity(item) ? 'text-green-600' : 'text-amber-600'"
                                            >
                                                {{ getSentQuantity(item) || 0 }}
                                            </span>
                                            <span class="text-xs text-gray-500 font-normal">{{ item.unit || 'وحدة' }}</span>
                                            <Icon v-if="getSentQuantity(item) >= getRequestedQuantity(item)" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
                                            <Icon v-else icon="solar:danger-circle-bold" class="w-5 h-5 text-amber-500" />
                                        </div>
                                    </div>
                                    
                                    <!-- الكمية المستلمة (تظهر فقط عند الاستلام) -->
                                    <div v-if="isReceivedStatus" class="text-center pl-4 border-r border-gray-100">
                                        <span class="text-xs text-gray-400 block mb-1">مستلم</span>
                                        <div class="flex items-center gap-1">
                                            <span 
                                                class="font-bold text-lg"
                                                :class="getReceivedQuantity(item) >= getSentQuantity(item) ? 'text-green-600' : 'text-orange-600'"
                                            >
                                                {{ getReceivedQuantity(item) ?? 0 }}
                                            </span>
                                            <span class="text-xs text-gray-500 font-normal">{{ item.unit || 'وحدة' }}</span>
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

                <!-- Rejection Reason -->
                <div v-if="requestDetails.rejectionReason" class="bg-red-50 border border-red-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-red-700 mb-2 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب الرفض
                    </h3>
                    <p class="text-red-800 font-medium leading-relaxed">{{ requestDetails.rejectionReason }}</p>
                    <p v-if="requestDetails.rejectedAt" class="text-red-600 text-sm mt-3 flex items-center gap-1">
                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                        بتاريخ: {{ formatDate(requestDetails.rejectedAt) }}
                    </p>
                </div>

                <!-- Notes -->
                <div v-if="requestDetails.storekeeperNotes || requestDetails.supplierNotes || shouldShowConfirmationNotes || requestDetails.notes" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الملاحظات
                    </h3>

                    <!-- ملاحظة عند إنشاء الطلب -->
                    <div v-if="requestDetails.storekeeperNotes" class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            {{ requestDetails.storekeeperNotesSource === 'pharmacist' ? 'من الصيدلي' : requestDetails.storekeeperNotesSource === 'department' ? 'من مدير القسم' : 'ملاحظة الطلب' }}
                        </h4>
                        <p class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.storekeeperNotes }}</p>
                    </div>

                    <!-- ملاحظة عند الإرسال من storekeeper -->
                    <div v-if="requestDetails.supplierNotes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                        <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                            من مدير المخزن
                        </h4>
                        <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.supplierNotes }}</p>
                    </div>

                    <!-- ملاحظة عند تأكيد الاستلام (تظهر فقط للمستخدم الذي كتبها) -->
                    <div v-if="shouldShowConfirmationNotes" class="p-4 bg-purple-50 border border-purple-100 rounded-xl">
                        <h4 class="font-bold text-purple-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                            ملاحظة تأكيد الاستلام
                        </h4>
                        <p class="text-purple-800 text-sm leading-relaxed">{{ requestDetails.confirmation?.confirmationNotes || requestDetails.confirmationNotes }}</p>
                    </div>

                    <!-- للتوافق مع الكود القديم -->
                    <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && requestDetails.confirmation?.notes && !requestDetails.confirmation?.confirmationNotes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                        <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                            ملاحظة الإرسال
                        </h4>
                        <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.confirmation.notes }}</p>
                    </div>

                    <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && requestDetails.notes && !requestDetails.confirmation?.notes" class="p-4 bg-gray-50 border border-gray-100 rounded-xl">
                        <h4 class="font-bold text-[#2E5077] mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5 text-[#4DA1A9]" />
                            ملاحظة الطلب الأصلية
                        </h4>
                        <p class="text-gray-600 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
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
import { ref, computed, watch, onMounted } from 'vue';
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    requestId: {
        type: [Number, String],
        default: null
    },
    requestData: {
        type: Object,
        default: () => ({ 
            id: null, 
            date: '', 
            status: '', 
            items: [], 
            notes: '',
            storekeeperNotes: null,
            storekeeperNotesSource: null,
            supplierNotes: null,
            confirmation: null,
            confirmationNotesSource: null 
        })
    }
});

const emit = defineEmits(['close']);

// حالة الطلب
const requestDetails = ref({
    id: props.requestData.id,
    date: props.requestData.date,
    status: props.requestData.status,
    items: props.requestData.items,
    notes: props.requestData.notes || '',
    storekeeperNotes: props.requestData.storekeeperNotes || null,
    storekeeperNotesSource: props.requestData.storekeeperNotesSource || null,
    supplierNotes: props.requestData.supplierNotes || null,
    confirmation: props.requestData.confirmation ? {
        ...props.requestData.confirmation,
        confirmationNotes: props.requestData.confirmation.confirmationNotes || props.requestData.confirmationNotes || null
    } : null,
    confirmationNotes: props.requestData.confirmationNotes || (props.requestData.confirmation?.confirmationNotes) || null,
    confirmationNotesSource: props.requestData.confirmationNotesSource || null,
    rejectionReason: props.requestData.rejectionReason || null,
    rejectedAt: props.requestData.rejectedAt || null
});

// دالة مساعدة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        // Format: DD/MM/YYYY HH:mm (English numbers)
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
     
        return `${day}/${month}/${year} `;
    } catch {
        return dateString;
    }
};

// نوع المستخدم الحالي
const currentUserType = ref('');

onMounted(() => {
    // جلب نوع المستخدم من localStorage
    const userRole = localStorage.getItem('user_role') || '';
    const userData = localStorage.getItem('user_data');
    
    if (userData) {
        try {
            const parsed = JSON.parse(userData);
            currentUserType.value = parsed.type || userRole || '';
        } catch {
            currentUserType.value = userRole || '';
        }
    } else {
        currentUserType.value = userRole || '';
    }
    
    // تحويل نوع المستخدم إلى التنسيق المتوقع
    if (currentUserType.value === 'pharmacist') {
        currentUserType.value = 'pharmacist';
    } else if (currentUserType.value === 'department_head' || currentUserType.value === 'department_admin') {
        currentUserType.value = 'department';
    }
});

// computed property للتحقق من إمكانية عرض ملاحظة التأكيد
const shouldShowConfirmationNotes = computed(() => {
    // إذا لم تكن هناك ملاحظة تأكيد، لا نعرضها
    if (!requestDetails.value.confirmation?.confirmationNotes && !requestDetails.value.confirmationNotes) {
        return false;
    }
    
    // إذا لم يكن هناك مصدر محدد، نعرضها (للتوافق مع الكود القديم)
    if (!requestDetails.value.confirmationNotesSource) {
        return true;
    }
    
    // إذا كان المستخدم الحالي هو نفس مصدر الملاحظة، نعرضها
    // pharmacist يرى ملاحظته فقط، department يرى ملاحظته فقط
    if (currentUserType.value === 'pharmacist' && requestDetails.value.confirmationNotesSource === 'pharmacist') {
        return true;
    }
    if (currentUserType.value === 'department' && requestDetails.value.confirmationNotesSource === 'department') {
        return true;
    }
    
    // إذا كان المستخدم مختلف عن مصدر الملاحظة، لا نعرضها
    return false;
});

// Watch لتحديث البيانات
watch(() => props.requestData, (newVal) => {
    if (newVal) {
        requestDetails.value = {
            id: newVal.id,
            date: newVal.date,
            status: newVal.status,
            items: newVal.items || [],
            notes: newVal.notes || '',
            storekeeperNotes: newVal.storekeeperNotes || null,
            storekeeperNotesSource: newVal.storekeeperNotesSource || null,
            supplierNotes: newVal.supplierNotes || null,
            confirmation: newVal.confirmation ? {
                ...newVal.confirmation,
                confirmationNotes: newVal.confirmation.confirmationNotes || newVal.confirmationNotes || null
            } : null,
            confirmationNotes: newVal.confirmationNotes || (newVal.confirmation?.confirmationNotes) || null,
            confirmationNotesSource: newVal.confirmationNotesSource || null,
            rejectionReason: newVal.rejectionReason || null,
            rejectedAt: newVal.rejectedAt || null
        };
    }
}, { immediate: true, deep: true });

// دوال مساعدة
// دالة لاستخراج الكمية المطلوبة
const getRequestedQuantity = (item) => {
    return item.requested_qty || item.requestedQty || item.quantity || 0;
};

// دالة لاستخراج الكمية المرسلة (من أمين المخزن)
const getSentQuantity = (item) => {
    // approved_qty = الكمية المرسلة من أمين المخزن
    // fulfilled_qty = الكمية المستلمة (ليست الكمية المرسلة!)
    
    // أولاً: approved_qty (الكمية المرسلة الفعلية من أمين المخزن)
    if (item.approved_qty !== null && item.approved_qty !== undefined) {
        const val = Number(item.approved_qty);
        if (!isNaN(val) && val > 0) {
            return val;
        }
    }
    if (item.approvedQty !== null && item.approvedQty !== undefined) {
        const val = Number(item.approvedQty);
        if (!isNaN(val) && val > 0) {
            return val;
        }
    }
    if (item.sentQuantity !== null && item.sentQuantity !== undefined) {
        const val = Number(item.sentQuantity);
        if (!isNaN(val) && val > 0) {
            return val;
        }
    }
    return 0;
};

const getReceivedQuantity = (item) => {
    // الكمية المستلمة هي fulfilled_qty بعد تأكيد الاستلام من StoreKeeper
    // لكن يجب التمييز بين fulfilled_qty من Supplier (مرسل) و fulfilled_qty بعد تأكيد الاستلام (مستلم)
    // الحل: نستخدم receivedQuantity إذا كان موجوداً (الكمية المستلمة الفعلية)
    // وإلا نستخدم fulfilled_qty فقط إذا تم تأكيد الاستلام (isReceivedStatus)
    
    // أولوية: receivedQuantity من confirmation.receivedItems (الكمية المستلمة الفعلية)
    if (requestDetails.value.confirmation?.receivedItems) {
        const receivedItem = requestDetails.value.confirmation.receivedItems.find(
            ri => (item.id && ri.id === item.id) || ri.name === item.name
        );
        if (receivedItem !== undefined && receivedItem.receivedQuantity !== null && receivedItem.receivedQuantity !== undefined) {
            const val = Number(receivedItem.receivedQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    
    // ثانياً: receivedQuantity من item مباشرة
    if (item.receivedQuantity !== null && item.receivedQuantity !== undefined && item.receivedQuantity !== '') {
        const val = Number(item.receivedQuantity);
        if (!isNaN(val) && val >= 0) {
            return val;
        }
    }
    
    // ثالثاً: fulfilledQty أو fulfilled_qty فقط إذا تم تأكيد الاستلام (isReceivedStatus)
    // لأن fulfilled_qty قد يكون من Supplier (مرسل) وليس مستلم
    if (isReceivedStatus.value) {
        if (item.fulfilledQty !== null && item.fulfilledQty !== undefined && item.fulfilledQty !== '') {
            const val = Number(item.fulfilledQty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        
        if (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined && item.fulfilled_qty !== '') {
            const val = Number(item.fulfilled_qty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    
    return 0;
};

const isFullyReceived = (item) => {
    const receivedQty = getReceivedQuantity(item);
    if (receivedQty === null) return false;
    
    const requiredQty = getRequestedQuantity(item);
    
    return receivedQty >= requiredQty;
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    return status && (
        status.includes('تم الإرسال') || 
        status.includes('مُرسَل') || 
        status.includes('مؤكد') || 
        status.includes('قيد الاستلام') ||
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        status === 'approved' ||
        status === 'fulfilled'
    );
});

// التحقق من أن الحالة هي "تم الإستلام"
const isReceivedStatus = computed(() => {
    const status = requestDetails.value.status;
    // التحقق من الحالة النصية
    const statusText = status?.toString().toLowerCase() || '';
    const isReceived = statusText.includes('تم الاستلام') || 
                       statusText.includes('تم الإستلام') || 
                       statusText.includes('مستلم') ||
                       statusText.includes('مُستلَم') ||
                       status === 'تم الاستلام' ||
                       status === 'تم الإستلام' ||
                       status === 'delivered';
    
    // التحقق من وجود confirmation
    const hasConfirmation = requestDetails.value.confirmation !== null && 
                           requestDetails.value.confirmation !== undefined;
    
    // التحقق من وجود receivedItems في confirmation
    const hasReceivedItems = requestDetails.value.confirmation?.receivedItems && 
                            Array.isArray(requestDetails.value.confirmation.receivedItems) &&
                            requestDetails.value.confirmation.receivedItems.length > 0;
    
    // التحقق من أن fulfilled_qty موجود في items (يعني تم الاستلام)
    const hasFulfilledQty = requestDetails.value.items && 
                            requestDetails.value.items.some(item => 
                                (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined && item.fulfilled_qty > 0) ||
                                (item.fulfilledQty !== null && item.fulfilledQty !== undefined && item.fulfilledQty > 0) ||
                                (item.receivedQuantity !== null && item.receivedQuantity !== undefined && item.receivedQuantity > 0)
                            );
    
    return isReceived || hasConfirmation || hasReceivedItems || hasFulfilledQty;
});

// تنسيق الحالة
const statusClass = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return 'bg-gray-200 text-gray-700';
    if (status.includes('تم الاستلام') || status.includes('مُستلَمَة')) {
        return 'bg-[#4DA1A9]/10 text-[#4DA1A9]';
    }
    if (status === 'مؤكد') {
        return 'bg-green-100 text-green-700';
    }
    if (status === 'قيد الانتظار' || status === 'قيد المراجعة') {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (status === 'ملغي' || status === 'مرفوضة') {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-gray-200 text-gray-700';
});

const closeModal = () => {
    emit('close');
};
</script>