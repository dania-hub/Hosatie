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
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.date) || 'غير محدد' }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">حالة الطلب</span>
                            <span :class="statusClass" class="px-3 py-1 rounded-lg text-sm font-bold">{{ requestDetails.status || 'جديد' }}</span>
                        </div>
                        
                        <div v-if="requestDetails.confirmation?.confirmedAt" class="p-4 bg-gray-50 rounded-xl flex justify-between items-center md:col-span-2">
                            <span class="text-gray-500 font-medium">تاريخ الإرسال/التأكيد</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestDetails.confirmation.confirmedAt) }}</span>
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

                    <div class="bg-white rounded-[1.5rem] border border-gray-200/60 overflow-hidden shadow-sm">
                        <div v-if="requestDetails.items && requestDetails.items.length > 0" class="divide-y divide-gray-100">
                            <div 
                                v-for="(item, index) in requestDetails.items" 
                                :key="index"
                                class="p-5 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 hover:bg-gray-50/50 transition-colors"
                            >
                                <!-- Item Info -->
                                <div class="flex-1 w-full lg:w-auto">
                                    <div class="flex items-start gap-4 mb-2">
                                        <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center border border-slate-100 shadow-sm shrink-0">
                                            <Icon icon="solar:pill-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-[#2E5077] text-lg leading-tight">{{ item.name }}</h4>
                                            <div class="flex gap-2 mt-2 flex-wrap">
                                                <span v-if="item.strength || item.dosage" class="text-[10px] bg-blue-50 text-blue-600 px-2 py-1 rounded-lg font-bold border border-blue-100">
                                                    {{ item.strength || item.dosage }}
                                                </span>
                                                <span v-if="item.unit" class="text-[10px] bg-green-50 text-green-600 px-2 py-1 rounded-lg font-bold border border-green-100">
                                                    {{ item.unit }}
                                                </span>
                                                <span v-if="item.type || item.form" class="text-[10px] bg-slate-50 text-slate-500 px-2 py-1 rounded-lg font-bold border border-slate-100">
                                                    {{ item.type || item.form }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 w-full lg:w-auto overflow-x-auto pb-2 lg:pb-0">
                                    <!-- Requested Qty -->
                                    <div class="px-4 py-3 bg-slate-50 rounded-2xl border border-slate-100 flex flex-col items-center min-w-[100px]">
                                        <span class="text-[10px] text-slate-400 font-bold mb-1 uppercase tracking-wider">مطلوب</span>
                                        <div class="flex items-center gap-1 font-bold">
                                            <span 
                                                class="text-gray-700 text-sm"
                                                v-html="getFormattedQuantity(item.quantity || item.requestedQuantity || item.requested_qty || 0, item)"
                                            ></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Sent Qty & Details -->
                                    <div v-if="hasSentQuantity(item)" class="px-4 py-3 bg-blue-50/50 rounded-2xl border border-blue-100 flex flex-col items-center min-w-[160px] relative group">
                                        <span class="text-[10px] text-blue-400 font-bold mb-1 uppercase tracking-wider flex items-center gap-1">
                                            مرسل
                                            <Icon v-if="getSentQuantity(item) >= (item.quantity || item.requestedQuantity || item.requested_qty || 0)" icon="solar:check-circle-bold" class="w-3 h-3 text-green-500" />
                                            <Icon v-else icon="solar:info-circle-bold" class="w-3 h-3 text-amber-500" />
                                        </span>
                                        <div class="flex items-center gap-1 mb-1 font-bold">
                                            <span 
                                                class="text-sm"
                                                :class="getSentQuantity(item) >= (item.quantity || item.requestedQuantity || item.requested_qty || 0) ? 'text-green-600' : 'text-amber-600'"
                                                v-html="getFormattedQuantity(getSentQuantity(item) || 0, item)"
                                            ></span>
                                        </div>
                                        
                                        <!-- Batch Info -->
                                        <div class="flex flex-col gap-1 w-full mt-1 pt-1 border-t border-blue-100/50">
                                             <div v-if="getBatchNumber(item)" class="flex items-center gap-1.5 text-[10px] text-blue-600 bg-white/60 px-1.5 py-0.5 rounded-md">
                                                <Icon icon="solar:tag-bold-duotone" class="w-3 h-3 text-blue-400" />
                                                <span class="font-mono font-bold">{{ getBatchNumber(item) }}</span>
                                             </div>
                                             <div v-if="getExpiryDate(item)" class="flex items-center gap-1.5 text-[10px] text-purple-600 bg-white/60 px-1.5 py-0.5 rounded-md">
                                                <Icon icon="solar:calendar-bold-duotone" class="w-3 h-3 text-purple-400" />
                                                <span class="font-bold">{{ formatDateShort(getExpiryDate(item)) }}</span>
                                             </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Received Qty -->
                                    <div v-if="hasReceivedQuantity(item)" class="px-4 py-3 bg-purple-50/50 rounded-2xl border border-purple-100 flex flex-col items-center min-w-[100px]">
                                        <span class="text-[10px] text-purple-400 font-bold mb-1 uppercase tracking-wider">مستلم</span>
                                        <div class="flex items-center gap-1 font-bold">
                                            <span 
                                                class="text-sm"
                                                :class="getReceivedQuantity(item) >= getSentQuantity(item) ? 'text-green-600' : 'text-amber-600'"
                                                v-html="getFormattedQuantity(getReceivedQuantity(item) || 0, item)"
                                            ></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-gray-400 bg-gray-50/30">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <Icon icon="solar:box-minimalistic-broken" class="w-8 h-8 text-gray-300" />
                            </div>
                            <p class="font-bold">لا توجد أدوية في هذا الطلب</p>
                        </div>
                    </div>
                </div>
                 <!-- <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            رسالة المورد
                        </h4>
                        <p v-if="requestDetails.notes" class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
                        <p v-else class="text-blue-400 text-sm leading-relaxed italic">لا توجد رسالة مرفقة</p>
                    </div> -->
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
                <div v-if="requestDetails.storekeeperNotes || requestDetails.supplierNotes || requestDetails.notes || (requestDetails.confirmation && requestDetails.confirmation.notes)" class="space-y-4">
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

                    <!-- للتوافق مع الكود القديم -->
                    <div v-if="!requestDetails.storekeeperNotes && !requestDetails.supplierNotes && requestDetails.confirmation?.notes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                        <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                            ملاحظة الإرسال
                        </h4>
                        <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.confirmation.notes }}</p>
                    </div>

                    <!-- Supplier Message (Always Visible) -->
                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            رسالة المورد
                        </h4>
                        <div v-if="Array.isArray(requestDetails.notes) && requestDetails.notes.length > 0" class="space-y-3">
                            <div v-for="(msg, index) in requestDetails.notes" :key="index" class="bg-white/60 p-3 rounded-lg border border-blue-100/50">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-bold" :class="msg.by === 'supplier_admin' ? 'text-green-700' : 'text-blue-700'">
                                        {{ msg.by === 'supplier_admin' ? (msg.user_name || 'المورد') : 'الإدارة' }}
                                    </span>
                                    <span class="text-[10px] text-gray-400" dir="ltr">{{ formatDate(msg.created_at) }}</span>
                                </div>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
                            </div>
                        </div>
                        <p v-else-if="requestDetails.notes && typeof requestDetails.notes === 'string'" class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
                        <p v-else class="text-blue-400 text-sm leading-relaxed italic">لا توجد رسالة مرفقة</p>
                    </div>
                </div>
                
                <!-- Confirmation Details -->
                <div v-if="requestDetails.confirmation || requestDetails.confirmationDetails" class="bg-purple-50 border border-purple-100 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-purple-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:user-check-bold-duotone" class="w-6 h-6" />
                        تفاصيل التأكيد
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div v-if="(requestDetails.confirmation || requestDetails.confirmationDetails)?.confirmedBy">
                            <span class="text-purple-600 text-sm block mb-1">تم التأكيد بواسطة</span>
                            <span class="font-bold text-purple-900">{{ (requestDetails.confirmation || requestDetails.confirmationDetails).confirmedBy }}</span>
                        </div>
                        <div v-if="(requestDetails.confirmation || requestDetails.confirmationDetails)?.confirmedAt">
                            <span class="text-purple-600 text-sm block mb-1">تاريخ التأكيد</span>
                            <span class="font-bold text-purple-900">{{ formatDate((requestDetails.confirmation || requestDetails.confirmationDetails).confirmedAt) }}</span>
                        </div>
                        <div v-if="(requestDetails.confirmation || requestDetails.confirmationDetails)?.totalItemsSent" class="sm:col-span-2">
                            <span class="text-purple-600 text-sm block mb-1">إجمالي الوحدات المرسلة</span>
                            <span class="font-bold text-purple-900 text-lg">{{ (requestDetails.confirmation || requestDetails.confirmationDetails).totalItemsSent }}</span>
                        </div>
                        
                        <!-- ملاحظة تأكيد الاستلام -->
                        <div v-if="(requestDetails.confirmation || requestDetails.confirmationDetails)?.confirmationNotes || (requestDetails.confirmationNotes && !requestDetails.confirmation && !requestDetails.confirmationDetails)" class="sm:col-span-2 p-4 bg-white/50 rounded-xl border border-purple-100/50">
                            <h4 class="font-bold text-purple-700 mb-2 flex items-center gap-2">
                                <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                                {{ requestDetails.confirmationNotesSource === 'pharmacist' ? 'من الصيدلي' : requestDetails.confirmationNotesSource === 'department' ? 'من مدير القسم' : 'ملاحظة تأكيد الاستلام' }}
                            </h4>
                            <p class="text-purple-800 text-sm leading-relaxed">{{ (requestDetails.confirmation || requestDetails.confirmationDetails)?.confirmationNotes || requestDetails.confirmationNotes }}</p>
                        </div>
                        
                    
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
import { ref, computed, watch } from 'vue';
import { Icon } from "@iconify/vue";

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
            supplierNotes: null,
            confirmation: null,
            confirmationDetails: null,
            rejectionReason: null,
            priority: null
        })
    }
});

const emit = defineEmits(['close']);

const requestDetails = ref({ ...props.requestData });

// Watch لتحديث البيانات
watch(() => props.requestData, (newVal) => {
    if (newVal) {
        requestDetails.value = {
            ...newVal,
            rejectionReason: newVal.rejectionReason || null,
            rejectedAt: newVal.rejectedAt || null,
            notes: newVal.notes || '',
            storekeeperNotes: newVal.storekeeperNotes || null,
            storekeeperNotesSource: newVal.storekeeperNotesSource || null,
            supplierNotes: newVal.supplierNotes || null,
            confirmationNotes: newVal.confirmationNotes || (newVal.confirmation?.confirmationNotes) || null,
            confirmationNotesSource: newVal.confirmationNotesSource || null
        };
    }
}, { immediate: true, deep: true });
const getFormattedQuantity = (quantity, item) => {
    if (!item) return quantity;
    const unit = item.unit || 'حبة';
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';
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
// دالة تنسيق التاريخ
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
    // محاولة الحصول من confirmationDetails أولاً
    if (requestDetails.value.confirmationDetails?.receivedItems) {
        const sentItem = requestDetails.value.confirmationDetails.receivedItems.find(
            si => si.id === item.id || si.drugId === item.id
        );
        if (sentItem && sentItem.sentQuantity) {
            return sentItem.sentQuantity;
        }
    }
    // محاولة الحصول من confirmation
    if (requestDetails.value.confirmation?.items) {
        const sentItem = requestDetails.value.confirmation.items.find(
            si => si.id === item.id || si.drugId === item.id
        );
        if (sentItem) {
            return sentItem.sentQuantity || sentItem.receivedQuantity || 0;
        }
    }
    // ثم من الحقول المباشرة في item
    return item.sentQuantity || item.fulfilled_qty || item.approved_qty || 0;
};

// دالة للتحقق من وجود كمية مستلمة
const hasReceivedQuantity = (item) => {
    const receivedQty = getReceivedQuantity(item);
    return receivedQty > 0;
};

// دالة لاستخراج تاريخ انتهاء الصلاحية
const getExpiryDate = (item) => {
    // 1. محاولة الحصول من المتغير المباشر (الذي تم تمريره من Requests.vue)
    if (item.expiryDate || item.expiry_date) {
        return item.expiryDate || item.expiry_date;
    }
    
    // 2. محاولة الحصول من confirmation (التأكيد السابق)
    if (requestDetails.value.confirmation?.items) {
        const sentItem = requestDetails.value.confirmation.items.find(
            si => si.id === item.id || si.drugId === item.id || si.drugId === item.drugId
        );
        if (sentItem && (sentItem.expiryDate || sentItem.expiry_date)) {
            return sentItem.expiryDate || sentItem.expiry_date;
        }
    }
    
    return null;
};

// دالة لاستخراج رقم الدفعة
const getBatchNumber = (item) => {
    // 1. محاولة الحصول من المتغير المباشر
    if (item.batchNumber || item.batch_number) {
        return item.batchNumber || item.batch_number;
    }
    
    // 2. محاولة يس من confirmation
    if (requestDetails.value.confirmation?.items) {
        const sentItem = requestDetails.value.confirmation.items.find(
            si => si.id === item.id || si.drugId === item.id || si.drugId === item.drugId
        );
        if (sentItem && (sentItem.batchNumber || sentItem.batch_number)) {
            return sentItem.batchNumber || sentItem.batch_number;
        }
    }
    
    // محاولة استنتاج افتراضي إذا كانت مرسلة
    if (hasSentQuantity(item)) {
        // إذا كان هناك requestDetails.id، يمكن استنتاج الدفعة التلقائية
        return requestDetails.value.id ? `RE-${requestDetails.value.id}` : null;
    }
    
    return null;
};

// تنسيق تاريخ قصير
const formatDateShort = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', { // DD/MM/YYYY formatting
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return dateString;
    }
};

// دالة لاستخراج الكمية المستلمة
const getReceivedQuantity = (item) => {
    // أولاً: محاولة الحصول من الحقول المباشرة في item (الأولوية الأولى)
    if (item.receivedQuantity !== null && item.receivedQuantity !== undefined) {
        return item.receivedQuantity;
    }
    
    // ثانياً: محاولة الحصول من confirmationDetails
    if (requestDetails.value.confirmationDetails?.receivedItems) {
        const receivedItem = requestDetails.value.confirmationDetails.receivedItems.find(
            ri => ri.id === item.id || ri.drugId === item.id
        );
        if (receivedItem && receivedItem.receivedQuantity !== null && receivedItem.receivedQuantity !== undefined) {
            return receivedItem.receivedQuantity;
        }
    }
    
    // ثالثاً: محاولة الحصول من confirmation
    if (requestDetails.value.confirmation?.items) {
        const receivedItem = requestDetails.value.confirmation.items.find(
            ri => ri.id === item.id || ri.drugId === item.id
        );
        if (receivedItem && receivedItem.receivedQuantity !== null && receivedItem.receivedQuantity !== undefined) {
            return receivedItem.receivedQuantity;
        }
    }
    
    // أخيراً: إذا لم نجد receivedQuantity، نرجع 0
    return 0;
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    const statusOriginal = requestDetails.value.statusOriginal;
    return status && (
        status.includes('تم الإرسال') || 
        status.includes('مُرسَل') || 
        status.includes('مؤكد') || 
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        statusOriginal === 'fulfilled' ||
        statusOriginal === 'approved'
    );
});

// تحديد حالة الاستلام
const isReceivedStatus = computed(() => {
    const status = requestDetails.value.status;
    const statusOriginal = requestDetails.value.statusOriginal;
    const confirmationDetails = requestDetails.value.confirmationDetails;
    const confirmation = requestDetails.value.confirmation;
    
    // التحقق من وجود تفاصيل التأكيد
    if (confirmationDetails?.receivedItems && confirmationDetails.receivedItems.length > 0) {
        return true;
    }
    if (confirmation?.receivedItems && confirmation.receivedItems.length > 0) {
        return true;
    }
    
    // التحقق من الحالة
    return status && (
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        statusOriginal === 'delivered'
    );
});

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
    
    if (status.includes('تم الاستلام') || status.includes('مُستلَم') || status === 'تم الإستلام') {
        return 'bg-green-100 text-green-700';
    }
    if (status.includes('مؤكد') || status.includes('تم الإرسال')) {
        return 'bg-blue-100 text-blue-700';
    }
    if (status.includes('قيد الانتظار') || status.includes('قيد المراجعة') || status.includes('قيد التجهيز')) {
        return 'bg-yellow-100 text-yellow-700';
    }
    if (status.includes('ملغي') || status.includes('مرفوضة')) {
        return 'bg-red-100 text-red-700';
    }
    return 'bg-gray-200 text-gray-700';
});

const closeModal = () => {
    emit('close');
};
</script>