<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/40 flex items-center justify-center p-4"
    >
        <div
            class="relative bg-white rounded-xl shadow-3xl w-full max-w-2xl mx-auto my-10 transform transition-all duration-300 scale-100 opacity-100 dark:bg-gray-800"
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
                        icon="tabler:truck-delivery"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
 تأكيد استلام الشحنة رقم: {{ requestData.id || '...' }}
                </h3>
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-1 rounded-full hover:bg-[#B8D7D9]/30"
                    :disabled="props.isLoading"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- المحتوى -->
            <div class="p-5 sm:px-6 sm:py-5 bg-[#F6F4F0] space-y-8 max-h-[70vh] overflow-y-auto">
                
                <!-- حالة التحميل -->
                <div v-if="isLoading" class="text-center py-8">
                    <Icon icon="eos-icons:loading" class="w-12 h-12 text-[#4DA1A9] mx-auto mb-4" />
                    <p class="text-gray-600">جاري تأكيد الاستلام...</p>
                </div>
                
                <!-- بيانات الشحنة -->
                <div class="space-y-4 pt-1">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon
                            icon="tabler:info-square"
                            class="w-5 h-5 ml-2"
                        />
                        بيانات الشحنة
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">رقم الشحنة:</span>
                            <span class="mr-2 text-gray-700 font-mono">{{ requestData.id || 'غير محدد' }}</span>
                        </p>
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">تاريخ الطلب:</span>
                            <span class="mr-2 text-gray-700">{{ formatDate(requestData.date) || 'غير محدد' }}</span>
                        </p>
                    </div>
                </div>

                <!-- الكميات المستلمة -->
                <div class="mt-8">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:checklist" class="w-5 h-5 ml-2" />
                        الكميات المستلمة لكل صنف
                    </h3>

                    <div
                        class="p-4 border border-[#B8D7D9] rounded-md bg-gray-50 max-h-80 overflow-y-auto shadow-inner"
                    >
                        <ul v-if="receivedItems.length > 0" class="list-none p-0 m-0 space-y-3">
                            <li
                                v-for="(item, index) in receivedItems"
                                :key="item.id || index"
                                class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center"
                            >
                                <div class="mb-3 md:mb-0 flex-1">
                                    <span class="font-extrabold text-[#2E5077] text-[15px] block">
                                        {{ item.name }}
                                    </span>
                                    <span class="text-xs text-gray-500 mt-1 block">
                                        المطلوب: **{{ item.originalQuantity }}** {{ item.unit || 'وحدة' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-4 w-full md:w-auto">
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-600 font-medium whitespace-nowrap">
                                            الكمية المستلمة:
                                        </label>
                                        <input
                                            type="number"
                                            v-model.number="item.receivedQuantity"
                                            :max="item.originalQuantity"
                                            min="0"
                                            class="w-24 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#4DA1A9] focus:border-[#4DA1A9] text-center transition-all duration-200 text-base"
                                            :class="{'border-green-400 focus:border-green-600': item.receivedQuantity === item.originalQuantity, 'border-amber-400 focus:border-amber-600': item.receivedQuantity !== item.originalQuantity && item.receivedQuantity > 0 && item.receivedQuantity < item.originalQuantity, 'border-red-400 focus:border-red-600': item.receivedQuantity === 0 && item.originalQuantity > 0 }"
                                            @input="validateQuantity(index, item.originalQuantity)"
                                            :disabled="props.isLoading || isConfirming"
                                        />
                                        <span class="text-gray-500 text-sm">
                                            {{ item.unit || 'وحدة' }}
                                        </span>
                                    </div>
                                    
                                    <div 
                                        :class="{
                                            'text-green-600 bg-green-100 p-2 rounded-full': item.receivedQuantity >= item.originalQuantity,
                                            'text-amber-600 bg-amber-100 p-2 rounded-full': item.receivedQuantity > 0 && item.receivedQuantity < item.originalQuantity,
                                            'text-red-600 bg-red-100 p-2 rounded-full': item.receivedQuantity === 0 && item.originalQuantity > 0
                                        }"
                                        class="flex items-center gap-1 text-sm font-bold min-w-[70px] justify-center transition-colors duration-200"
                                        v-if="item.receivedQuantity !== null"
                                    >
                                        <Icon 
                                            v-if="item.receivedQuantity >= item.originalQuantity"
                                            icon="tabler:circle-check" 
                                            class="w-5 h-5" 
                                        />
                                        <Icon 
                                            v-else-if="item.receivedQuantity > 0"
                                            icon="tabler:alert-triangle" 
                                            class="w-5 h-5" 
                                        />
                                        <Icon 
                                            v-else
                                            icon="tabler:circle-x" 
                                            class="w-5 h-5" 
                                        />
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-center text-gray-500 py-2">
                            لا توجد أدوية في هذا الطلب.
                        </p>
                    </div>
                </div>

                <!-- ملاحظات -->
                <div class="space-y-4 pt-2">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                        ملاحظات الاستلام (اختياري)
                    </h3>
                    <div class="border border-[#B8D7D9] rounded-lg bg-white shadow-sm transition-shadow focus-within:shadow-md focus-within:border-[#4DA1A9]">
                        <textarea
                            v-model="notes"
                            rows="3"
                            placeholder="أدخل أي ملاحظات حول الاستلام (مثل: نقص في الكمية، تغليف تالف، إلخ)..."
                            class="w-full px-4 py-3 border-none focus:outline-none text-sm text-[#2E5077] bg-transparent resize-none placeholder-gray-400"
                            :disabled="props.isLoading || isConfirming"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- الأزرار -->
            <div
                class="p-5 sm:px-6 sm:py-4 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] border-2 border-[#b7b9bb] hover:bg-[#d1d5db] font-semibold"
                    :disabled="props.isLoading || isConfirming"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmReceipt"
                    class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-12 w-35 rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9]"
                    :disabled="props.isLoading || isConfirming"
                >
                    <Icon v-if="isConfirming" icon="eos-icons:loading" class="w-5 h-5 ml-2" />
                    <Icon v-else icon="tabler:check" class="w-5 h-5 ml-2" />
                    {{ isConfirming ? 'جاري التأكيد...' : 'تأكيد الاستلام' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
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
            date: '', 
            status: '', 
            items: [] 
        })
    },
    isLoading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'confirm']);

// البيانات
const receivedItems = ref([]);
const notes = ref('');
const isConfirming = ref(false);

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

// تهيئة receivedItems
watch(() => props.requestData.items, (newItems) => {
    if (newItems && newItems.length > 0) {
        receivedItems.value = newItems.map(item => ({
            id: item.id || item.drugId,
            name: item.name || item.drugName,
            originalQuantity: Number(item.quantity || item.requestedQuantity),
            receivedQuantity: Number(item.quantity || item.requestedQuantity),
            unit: item.unit || 'وحدة'
        }));
        notes.value = '';
    }
}, { immediate: true, deep: true });

const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].receivedQuantity;

    if (isNaN(value) || value === null) {
        value = 0;
    }
    
    if (value > maxQuantity) {
        value = maxQuantity;
    }
    if (value < 0) {
        value = 0;
    }

    receivedItems.value[index].receivedQuantity = value;
};

const confirmReceipt = async () => {
    const hasInvalidQuantity = receivedItems.value.some(item => 
        item.receivedQuantity === null || item.receivedQuantity === undefined || item.receivedQuantity < 0
    );
    
    if (hasInvalidQuantity) {
        alert('يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف.');
        return;
    }
    
    isConfirming.value = true;
    
    try {
        const confirmationData = {
            receivedItems: receivedItems.value.map(item => ({
                id: item.id,
                name: item.name,
                originalQuantity: item.originalQuantity,
                receivedQuantity: item.receivedQuantity,
                unit: item.unit
            })),
            notes: notes.value.trim()
        };

        emit('confirm', confirmationData);
        
    } catch (error) {
        console.error('Error confirming receipt:', error);
        alert('حدث خطأ أثناء تأكيد الاستلام. يرجى المحاولة مرة أخرى.');
    } finally {
        isConfirming.value = false;
    }
};

const closeModal = () => {
    if (!props.isLoading) {
        emit('close');
    }
};
</script>

<style scoped>
/* الأنماط كما هي */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

textarea:focus {
    outline: none;
    box-shadow: none;
}

.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[70vh\] {
    max-height: 70vh;
}

.max-h-80 {
    max-height: 20rem;
}
</style>