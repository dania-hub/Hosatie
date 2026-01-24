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
                        البيانات الhhأساسية
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
                            <span :class="statusClass" class="px-3 py-1 rounded-lg text-sm font-bold">{{ requestDetails.status || 'جديد' }}</span>
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
                        <span v-if="isSentStatus && !isReceivedStatus" class="text-sm font-normal text-gray-400 mr-2">(مطلوب / مُرسل)</span>
                        <span v-if="isReceivedStatus" class="text-sm font-normal text-gray-400 mr-2">(مطلوب / مُرسل / مُستلم)</span>
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
                                    
                                    <!-- Batch Info -->
                                    <div class="flex items-center gap-3 mt-3">
                                        <div v-if="item.batch_number || item.batchNumber" class="bg-amber-50 text-amber-700 px-3 py-1.5 rounded-xl border border-amber-200 flex items-center gap-2">
                                            <div class="p-1 bg-amber-200/50 rounded-lg">
                                                <Icon icon="solar:tag-bold" class="w-4 h-4" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-[10px] uppercase font-bold text-amber-600 leading-none mb-0.5">رقم الدفعة</span>
                                                <span class="text-sm font-black">{{ item.batch_number || item.batchNumber }}</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="item.expiry_date || item.expiryDate" class="bg-purple-50 text-purple-700 px-3 py-1.5 rounded-xl border border-purple-200 flex items-center gap-2">
                                            <div class="p-1 bg-purple-200/50 rounded-lg">
                                                <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-[10px] uppercase font-bold text-purple-600 leading-none mb-0.5">تاريخ الانتهاء</span>
                                                <span class="text-sm font-black">{{ formatDate(item.expiry_date || item.expiryDate) }}</span>
                                            </div>
                                        </div>

                                        <div v-if="!(item.batch_number || item.batchNumber) && !(item.expiry_date || item.expiryDate)" class="text-xs text-gray-400 italic flex items-center gap-1">
                                            <Icon icon="solar:info-circle-linear" class="w-4 h-4" />
                                            لا توجد بيانات للدفعة أو تاريخ الانتهاء
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 md:gap-6 w-full md:w-auto justify-end flex-wrap">
                                    <!-- الكمية المطلوبة -->
                                    <div class="text-center">
                                        <span class="text-xs text-gray-400 block mb-1">مطلوب</span>
                                        <span class="font-bold text-[#4DA1A9] text-lg" v-html="getFormattedQuantity(getRequestedQuantity(item), item.unit, item.units_per_box || item.unitsPerBox)"></span>
                                    </div>
                                    
                                    <!-- الكمية المرسلة -->
                                    <div v-if="isSentStatus || isReceivedStatus" class="text-center pl-4 border-r border-gray-100">
                                        <span class="text-xs text-gray-400 block mb-1">مرسل</span>
                                        <div class="flex items-center gap-1">
                                            <span 
                                                class="font-bold text-lg"
                                                :class="getSentQuantity(item) >= getRequestedQuantity(item) ? 'text-green-600' : 'text-amber-600'"
                                                v-html="getFormattedQuantity(getSentQuantity(item), item.unit, item.units_per_box || item.unitsPerBox)"
                                            >
                                            </span>
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
                                                v-html="getFormattedQuantity(getReceivedQuantity(item), item.unit, item.units_per_box || item.unitsPerBox)"
                                            >
                                            </span>
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







  <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            رسالة المورد
                        </h4>
                        <p v-if="requestDetails.notes" class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
                        <p v-else class="text-blue-400 text-sm leading-relaxed italic">لا توجد رسالة مرفقة</p>
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

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-between items-center gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="printRequest" 
                    class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 flex items-center gap-2"
                >
                    <Icon icon="solar:printer-bold-duotone" class="w-5 h-5" />
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

const requestDetails = ref({ ...props.requestData });

// Watch لتحديث البيانات
watch(() => props.requestData, (newVal) => {
    if (newVal) {
        // التأكد من نسخ جميع البيانات بشكل صحيح
        requestDetails.value = {
            ...newVal,
            // التأكد من نسخ rejectionReason بشكل صحيح (حتى لو كان string فارغاً)
            rejectionReason: (newVal.rejectionReason && typeof newVal.rejectionReason === 'string' && newVal.rejectionReason.trim() !== '') 
                ? newVal.rejectionReason.trim() 
                : null,
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

// دالة تنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        // التحقق من أن التاريخ صحيح
        if (isNaN(date.getTime())) {
            return dateString; // إرجاع التاريخ الأصلي إذا كان غير صحيح
        }
        return date.toLocaleDateString('en-GB', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
    } catch {
        return dateString;
    }
};

const getFormattedQuantity = (quantity, unit = 'قرص', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';

    if (upb > 1) {
        const boxes = Math.floor(qty / upb);
        const remainder = qty % upb;
        
        if (boxes === 0 && qty > 0) return `${qty} ${unit}`;
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += ` <span class="text-[10px] text-gray-400 font-normal">و ${remainder} ${unit}</span>`;
        }
        return display;
    }
    return `${qty} ${unit}`;
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
    return item.requested_qty || item.quantity || 0;
};

// دالة لاستخراج الكمية المرسلة
const getSentQuantity = (item) => {
    // أولوية 1: الحصول من confirmation.receivedItems (يحتوي على الكمية المرسلة الأصلية من audit_log)
    if (requestDetails.value.confirmation?.receivedItems) {
        const receivedItem = requestDetails.value.confirmation.receivedItems.find(
            ri => (item.id && ri.id === item.id) || (ri.name && item.name && ri.name === item.name)
        );
        if (receivedItem && receivedItem.sentQuantity !== null && receivedItem.sentQuantity !== undefined) {
            const val = Number(receivedItem.sentQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    // أولوية 2: محاولة الحصول من confirmation.items (للتوافق مع الإصدارات القديمة)
    if (requestDetails.value.confirmation?.items) {
        const sentItem = requestDetails.value.confirmation.items.find(
            si => si.id === item.id || si.drugId === item.id
        );
        if (sentItem && sentItem.sentQuantity !== null && sentItem.sentQuantity !== undefined) {
            const val = Number(sentItem.sentQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    // أولوية 3: إذا تم تأكيد الاستلام، نستخدم sentQuantity من confirmation.receivedItems فقط
    // لأن fulfilled_qty بعد تأكيد الاستلام يكون الكمية المستلمة وليس المرسلة
    if (isReceivedStatus.value) {
        // إذا تم تأكيد الاستلام، لا نستخدم fulfilled_qty لأنه أصبح الكمية المستلمة
        // نستخدم approved_qty أو sentQuantity من item
        if (item.sentQuantity !== null && item.sentQuantity !== undefined && item.sentQuantity !== '') {
            const val = Number(item.sentQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        if (item.approved_qty !== null && item.approved_qty !== undefined && item.approved_qty !== '') {
            const val = Number(item.approved_qty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        if (item.approvedQty !== null && item.approvedQty !== undefined && item.approvedQty !== '') {
            const val = Number(item.approvedQty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    } else {
        // لم يتم تأكيد الاستلام بعد (قيد الاستلام): نستخدم approved_qty (الكمية المرسلة من المستودع)
        // approved_qty = الكمية التي أرسلها المستودع (storekeeper)
        // fulfilled_qty = الكمية المستلمة من الصيدلية (pharmacist) - تكون 0 عند الحالة "قيد الاستلام"
        if (item.approved_qty !== null && item.approved_qty !== undefined && item.approved_qty !== '') {
            const val = Number(item.approved_qty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        if (item.approvedQty !== null && item.approvedQty !== undefined && item.approvedQty !== '') {
            const val = Number(item.approvedQty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        if (item.sentQuantity !== null && item.sentQuantity !== undefined && item.sentQuantity !== '') {
            const val = Number(item.sentQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        // كحل احتياطي فقط: استخدام fulfilled_qty (لكن هذا يجب ألا يحدث عادة عند الحالة "قيد الاستلام")
        if (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined && item.fulfilled_qty !== '' && item.fulfilled_qty > 0) {
            const val = Number(item.fulfilled_qty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
        if (item.fulfilledQty !== null && item.fulfilledQty !== undefined && item.fulfilledQty !== '' && item.fulfilledQty > 0) {
            const val = Number(item.fulfilledQty);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    return 0;
};

// دالة لاستخراج الكمية المستلمة
const getReceivedQuantity = (item) => {
    // أولوية 1: receivedQuantity من confirmation.receivedItems (الكمية المستلمة الفعلية من audit_log)
    if (requestDetails.value.confirmation?.receivedItems) {
        const receivedItem = requestDetails.value.confirmation.receivedItems.find(
            ri => (item.id && ri.id === item.id) || (ri.name && item.name && ri.name === item.name)
        );
        if (receivedItem && receivedItem.receivedQuantity !== null && receivedItem.receivedQuantity !== undefined) {
            const val = Number(receivedItem.receivedQuantity);
            if (!isNaN(val) && val >= 0) {
                return val;
            }
        }
    }
    // أولوية 2: receivedQuantity مباشرة من item (إذا تم تمريره من الصفحة)
    if (item.receivedQuantity !== null && item.receivedQuantity !== undefined && item.receivedQuantity !== '') {
        const val = Number(item.receivedQuantity);
        if (!isNaN(val) && val >= 0) {
            return val;
        }
    }
    // ملاحظة: لا نستخدم fulfilled_qty هنا لأنه قد يكون الكمية المرسلة وليس المستلمة
    // فقط نستخدمه كحل أخير إذا لم نجد receivedQuantity في أي مكان
    // ولكن فقط إذا كانت الحالة "تم الاستلام" (لأن fulfilled_qty بعد تأكيد الاستلام يكون الكمية المستلمة)
    if (isReceivedStatus.value && (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined)) {
        const val = Number(item.fulfilled_qty);
        if (!isNaN(val) && val >= 0) {
            return val;
        }
    }
    return 0;
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    return status && (
        status.includes('تم الإرسال') || 
        status.includes('مُرسَل') || 
        status.includes('مؤكد') || 
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        status.includes('قيد الاستلام')
    );
});

// تحديد حالة الاستلام
const isReceivedStatus = computed(() => {
    const status = requestDetails.value.status;
    return status && (
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام' ||
        status === 'fulfilled'
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
    if (status.includes('قيد الانتظار') || status.includes('قيد المراجعة') || status.includes('قيد الاستلام')) {
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

// دالة لتنسيق الكمية بالعبوة للطباعة (نص بدون HTML)
const getFormattedQuantityForPrint = (quantity, unit = 'وحدة', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = unit === 'مل' ? 'عبوة' : 'علبة';

    if (upb > 1) {
        const boxes = Math.floor(qty / upb);
        const remainder = qty % upb;
        
        if (boxes === 0 && qty > 0) return `${qty} ${unit}`;
        
        let display = `${boxes} ${boxUnit}`;
        if (remainder > 0) {
            display += ` و ${remainder} ${unit}`;
        }
        return display;
    }
    return `${qty} ${unit}`;
};

// دالة الطباعة
const printRequest = () => {
    try {
        const printWindow = window.open('', '_blank', 'height=600,width=800');
        
        if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
            console.error('فشل في فتح نافذة الطباعة. يرجى السماح بفتح النوافذ المنبثقة.');
            return;
        }
        
        const details = requestDetails.value;
        
        // إعداد بيانات الأدوية للطباعة
        const itemsHtml = (details.items || []).map(item => {
            const requestedQty = getRequestedQuantity(item);
            const sentQty = getSentQuantity(item);
            const receivedQty = getReceivedQuantity(item);
            const unitsPerBox = item.units_per_box || item.unitsPerBox || 1;
            const unit = item.unit || 'وحدة';
            
            // استخدام getFormattedQuantityForPrint لعرض الكميات
            const formattedRequested = getFormattedQuantityForPrint(requestedQty, unit, unitsPerBox);
            const formattedSent = getFormattedQuantityForPrint(sentQty, unit, unitsPerBox);
            const formattedReceived = getFormattedQuantityForPrint(receivedQty, unit, unitsPerBox);
            
            // معلومات الدفعة وتاريخ الانتهاء
            let expiryInfo = '-';
            if (item.batch_number || item.batchNumber || item.expiry_date || item.expiryDate) {
                const batchStr = (item.batch_number || item.batchNumber) ? `دفعة: ${item.batch_number || item.batchNumber}` : '';
                const expiryStr = (item.expiry_date || item.expiryDate) ? `انتهاء: ${formatDate(item.expiry_date || item.expiryDate)}` : '';
                expiryInfo = [batchStr, expiryStr].filter(Boolean).join(' - ') || '-';
            }
            
            return `
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;">${item.name || 'غير محدد'}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedRequested}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSent}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedReceived}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; font-size: 11px;">${expiryInfo}</td>
                </tr>
            `;
        }).join('');
        
        const printContent = `
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
                    .notes-section { background-color: #f0f9ff; padding: 15px; border: 1px solid #4DA1A9; margin-top: 20px; border-radius: 5px; }
                    @media print {
                        button { display: none; }
                        @page { margin: 1cm; }
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
                        <span>${details.status || 'جديد'}</span>
                    </div>
                    ${details.confirmation?.confirmedAt ? `
                    <div class="info-row">
                        <span class="info-label">تاريخ التأكيد:</span>
                        <span>${formatDate(details.confirmation.confirmedAt)}</span>
                    </div>
                    ` : ''}
                </div>

                ${details.rejectionReason ? `
                    <div class="rejection-section">
                        <h3 style="color: #c00; margin-top: 0;">سبب الرفض:</h3>
                        <p>${details.rejectionReason}</p>
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
                            <th>الدفعة / تاريخ الانتهاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml || '<tr><td colspan="5" style="text-align: center;">لا توجد أدوية</td></tr>'}
                    </tbody>
                </table>

                ${details.storekeeperNotes || details.supplierNotes || details.notes ? `
                <div class="notes-section">
                    <h3 style="color: #2E5077; margin-top: 0;">الملاحظات</h3>
                    ${details.storekeeperNotes ? `<p><strong>ملاحظة الطلب:</strong> ${details.storekeeperNotes}</p>` : ''}
                    ${details.supplierNotes ? `<p><strong>من المورد:</strong> ${details.supplierNotes}</p>` : ''}
                    ${details.notes ? `<p><strong>ملاحظة:</strong> ${details.notes}</p>` : ''}
                    ${details.confirmation?.confirmationNotes || details.confirmationNotes ? `<p><strong>ملاحظة تأكيد الاستلام:</strong> ${details.confirmation?.confirmationNotes || details.confirmationNotes}</p>` : ''}
                </div>
                ` : ''}

                <p style="text-align: left; color: #666; font-size: 12px; margin-top: 30px;">
                    تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} ${new Date().toLocaleTimeString('ar-SA')}
                </p>
            </body>
            </html>
        `;
        
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // استخدام setTimeout لضمان تحميل المحتوى قبل الطباعة
        setTimeout(() => {
            if (printWindow && !printWindow.closed) {
                printWindow.focus();
                printWindow.print();
            }
        }, 250);
    } catch (error) {
        console.error('خطأ في عملية الطباعة:', error);
    }
};
</script>