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
            <!-- الهيدر -->
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-5 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-extrabold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:file-invoice"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
 تفاصيل طلب التوريد رقم:{{ requestDetails.id || '...' }}
                </h3>
                <span v-if="requestDetails.confirmation" class="confirmation-badge">
                    ✓ مُستلَم
                </span>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-1 rounded-full hover:bg-[#B8D7D9]/30"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- المحتوى -->
            <div class="p-5 sm:px-6 sm:py-5 space-y-8 max-h-[70vh] overflow-y-auto">

                <!-- البيانات الأساسية -->
                <div class="space-y-4 pt-1">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon
                            icon="tabler:info-square"
                            class="w-5 h-5 ml-2"
                        />
                        بيانات الطلب الأساسية
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-[#F6F4F0] p-4 rounded-lg border border-[#B8D7D9]/50">
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">تاريخ الطلب:</span>
                            <span class="mr-2 text-gray-700">{{ formatDate(requestDetails.date) || 'غير محدد' }}</span>
                        </p>
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">حالة الطلب:</span>
                            <span :class="statusClass" class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">{{ requestDetails.status || 'جديد' }}</span>
                        </p>
                        <p v-if="requestDetails.confirmation" class="text-right sm:col-span-2">
                            <span class="font-bold text-[#2E5077]">تاريخ الاستلام:</span>
                            <span class="mr-2 text-gray-700">
                                {{ requestDetails.confirmation.confirmedDate || 'غير محدد' }}
                                <span v-if="requestDetails.confirmation.confirmedTime">
                                    ({{ requestDetails.confirmation.confirmedTime }})
                                </span>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- الأدوية -->
                <div class="mt-8">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:package" class="w-5 h-5 ml-2" />
                        الأدوية المطلوبة والمُستلمة
                        <span class="mr-2 text-sm text-gray-500 font-normal">
                            ({{ requestDetails.confirmation ? 'مطلوب / مُستلم' : 'مطلوب' }})
                        </span>
                    </h3>

                    <div
                        class="p-4 border border-[#B8D7D9] rounded-md bg-[#F6F4F0] max-h-64 overflow-y-auto shadow-inner"
                    >
                        <ul v-if="requestDetails.items.length > 0" class="list-none p-0 m-0 space-y-3">
                            <li
                                v-for="(item, index) in requestDetails.items"
                                :key="index"
                                class="bg-white p-3 rounded-lg flex justify-between items-center text-sm border border-gray-200 shadow-sm transition duration-150 hover:shadow-md"
                            >
                                <div class="flex-1">
                                    <span class="font-extrabold text-[#2E5077] text-[15px]">{{ item.name }}</span>
                                    <div v-if="item.dosage" class="text-xs text-gray-500 mt-1">
                                        الجرعة: <span class="font-medium">{{ item.dosage }}</span>
                                    </div>
                                </div>
                                
                                <div class="text-left flex flex-col items-end">
                                    <div class="mb-1">
                                        <span class="font-medium text-gray-700">مطلوب:</span>
                                        <span class="text-[#4DA1A9] font-bold mr-1">
                                            {{ item.quantity }} {{ item.unit || 'وحدة' }}
                                        </span>
                                    </div>
                                    
                                    <div v-if="getReceivedQuantity(item) !== null && getReceivedQuantity(item) !== undefined" 
                                        :class="{'text-green-600': isFullyReceived(item), 'text-amber-600': !isFullyReceived(item)}"
                                        class="text-xs font-semibold flex items-center gap-1 mt-1 p-1 rounded"
                                    >
                                        <span class="font-medium">مستلم:</span>
                                        <span class="font-bold">
                                            {{ getReceivedQuantity(item) }} {{ item.unit || 'وحدة' }}
                                        </span>
                                        <Icon v-if="isFullyReceived(item)" 
                                                icon="tabler:circle-check" 
                                                class="w-4 h-4" />
                                        <Icon v-else 
                                                icon="tabler:alert-circle" 
                                                class="w-4 h-4" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-center text-gray-500 py-2">
                            لا توجد أدوية في هذا الطلب.
                        </p>
                    </div>
                </div>

                <!-- الملاحظات -->
                <div v-if="requestDetails.notes || (requestDetails.confirmation && requestDetails.confirmation.notes)" 
                     class="space-y-4 pt-2">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center">
                        <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                        الملاحظات
                    </h3>
                    
                    <div v-if="requestDetails.confirmation?.notes" class="p-4 border border-green-300 rounded-md bg-green-50 text-sm text-gray-800 shadow-inner">
                        <p class="font-bold text-green-700 mb-2 flex items-center">
                             <Icon icon="tabler:message-check" class="w-5 h-5 ml-2" /> ملاحظة الاستلام:
                        </p>
                        <p class="pr-2 border-r-2 border-green-500">{{ requestDetails.confirmation.notes }}</p>
                    </div>

                    <div v-if="requestDetails.notes" class="p-4 border border-[#B8D7D9] rounded-md bg-white text-sm text-gray-700 shadow-sm">
                        <p class="font-bold text-[#2E5077] mb-2 flex items-center">
                            <Icon icon="tabler:file-text" class="w-5 h-5 ml-2" /> ملاحظة الطلب الأصلية:
                        </p>
                        <p class="pr-2 border-r-2 border-[#4DA1A9]">{{ requestDetails.notes }}</p>
                    </div>
                </div>
                
            </div>

            <!-- الأزرار -->
            <div
                class="p-4 sm:pr-6 sm:pl-6 pt-3 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#d1d5db] font-semibold"
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
            confirmation: null 
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
    confirmation: props.requestData.confirmation || null
});

// دالة مساعدة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA');
    } catch {
        return dateString;
    }
};

// Watch لتحديث البيانات
watch(() => props.requestData, (newVal) => {
    if (newVal) {
        requestDetails.value = {
            id: newVal.id,
            date: newVal.date,
            status: newVal.status,
            items: newVal.items || [],
            notes: newVal.notes || '',
            confirmation: newVal.confirmation || null
        };
    }
}, { immediate: true, deep: true });

// دوال مساعدة
const getReceivedQuantity = (item) => {
    if (requestDetails.value.confirmation?.receivedItems) {
        const receivedItem = requestDetails.value.confirmation.receivedItems.find(
            ri => (item.id && ri.id === item.id) || ri.name === item.name
        );
        return receivedItem !== undefined ? Number(receivedItem.receivedQuantity) : null;
    }
    return item.receivedQuantity !== undefined ? Number(item.receivedQuantity) : null;
};

const isFullyReceived = (item) => {
    const receivedQty = getReceivedQuantity(item);
    const requiredQty = Number(item.quantity); 
    
    return receivedQty !== null && receivedQty >= requiredQty;
};

// تنسيق الحالة
const statusClass = computed(() => {
    const status = requestDetails.value.status;
    if (!status) return 'bg-gray-200 text-gray-700';
    if (status.includes('تم الاستلام') || status.includes('مُستلَمَة')) {
        return 'bg-[#4DA1A9] text-white';
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

<style scoped>
/* الأنماط كما هي */
.confirmation-badge {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-right: auto;
    box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3), 0 2px 4px -2px rgba(5, 150, 105, 0.3);
}

.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[70vh\] {
    max-height: 70vh;
}
</style>