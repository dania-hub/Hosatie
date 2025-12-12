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

                <!-- Items List -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الأدوية المطلوبة
                        <span v-if="isSentStatus" class="text-sm font-normal text-gray-400 mr-2">(مطلوب / مُرسل)</span>
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div v-if="requestDetails.items && requestDetails.items.length > 0" class="divide-y divide-gray-50">
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
                                
                                <div class="flex items-center gap-6 w-full md:w-auto justify-end">
                                    <div class="text-center">
                                        <span class="text-xs text-gray-400 block mb-1">مطلوب</span>
                                        <span class="font-bold text-[#4DA1A9] text-lg">{{ item.quantity }} <span class="text-xs text-gray-500 font-normal">{{ item.unit || 'وحدة' }}</span></span>
                                    </div>
                                    
                                    <div v-if="isSentStatus" class="text-center pl-4 border-r border-gray-100">
                                        <span class="text-xs text-gray-400 block mb-1">مرسل</span>
                                        <div class="flex items-center gap-1">
                                            <span 
                                                class="font-bold text-lg"
                                                :class="getSentQuantity(item) >= item.quantity ? 'text-green-600' : 'text-amber-600'"
                                            >
                                                {{ getSentQuantity(item) || 0 }}
                                            </span>
                                            <Icon v-if="getSentQuantity(item) >= item.quantity" icon="solar:check-circle-bold" class="w-5 h-5 text-green-500" />
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

                <!-- Notes -->
                <div v-if="requestDetails.notes || (requestDetails.confirmation && requestDetails.confirmation.notes)" class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الملاحظات
                    </h3>

                    <div v-if="requestDetails.confirmation?.notes" class="p-4 bg-green-50 border border-green-100 rounded-xl">
                        <h4 class="font-bold text-green-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-check-bold" class="w-5 h-5" />
                            ملاحظة الإرسال
                        </h4>
                        <p class="text-green-800 text-sm leading-relaxed">{{ requestDetails.confirmation.notes }}</p>
                    </div>

                    <div v-if="requestDetails.notes" class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <h4 class="font-bold text-blue-700 mb-2 flex items-center gap-2">
                            <Icon icon="solar:chat-round-line-bold" class="w-5 h-5" />
                            ملاحظة الطلب الأصلية
                        </h4>
                        <p class="text-blue-800 text-sm leading-relaxed">{{ requestDetails.notes }}</p>
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
            confirmation: null,
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
        requestDetails.value = { ...newVal };
    }
}, { immediate: true, deep: true });

// دالة تنسيق التاريخ
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

// دالة لاستخراج الكمية المرسلة
const getSentQuantity = (item) => {
    // محاولة الحصول من confirmation أولاً
    if (requestDetails.value.confirmation?.items) {
        const sentItem = requestDetails.value.confirmation.items.find(
            si => si.id === item.id || si.drugId === item.id
        );
        if (sentItem) {
            return sentItem.sentQuantity || sentItem.receivedQuantity || 0;
        }
    }
    // ثم من الحقول المباشرة
    return item.sentQuantity || item.receivedQuantity || item.provided || 0;
};

// تحديد حالة الإرسال
const isSentStatus = computed(() => {
    const status = requestDetails.value.status;
    return status && (
        status.includes('تم الإرسال') || 
        status.includes('مُرسَل') || 
        status.includes('مؤكد') || 
        status.includes('تم الاستلام') ||
        status === 'تم الإستلام'
    );
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