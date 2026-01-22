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
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تأكيد استلام الشحنة
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
                <!-- Loading State -->
                <div v-if="isLoading" class="py-12 text-center">
                    <Icon icon="svg-spinners:ring-resize" class="w-12 h-12 text-[#4DA1A9] mx-auto mb-4" />
                    <p class="text-gray-500 font-medium">جاري معالجة البيانات...</p>
                </div>
                
                <div v-else class="space-y-8">
                    <!-- Shipment Info -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                            <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            بيانات الشحنة
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">رقم الشحنة</span>
                                <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData.shipmentNumber || requestData.id || 'غير محدد' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                                <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                                <span class="font-bold text-[#2E5077]">{{ formatDate(requestData.date) || 'غير محدد' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            الكميات المستلمة
                        </h3>

                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                            <div v-if="receivedItems.length > 0" class="divide-y divide-gray-50">
                                <div 
                                    v-for="(item, index) in receivedItems" 
                                    :key="item.id || index"
                                    class="p-4 flex flex-col md:flex-row justify-between items-center gap-4 hover:bg-gray-50/50 transition-colors"
                                >
                                    <div class="flex-1 w-full md:w-auto">
                                        <div class="flex items-center gap-2">
                                            <Icon icon="solar:pill-bold" class="w-5 h-5 text-[#4DA1A9]" />
                                            <div class="font-bold text-[#2E5077] text-lg">{{ item.name }}</div>
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                                            <!-- Required & Sent Info (Formatted in Boxes) -->
                                            <div class="flex items-center gap-3 mt-1 flex-wrap">
                                                <div class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-lg border border-blue-100 flex items-center gap-1 font-bold">
                                                    <span>مطلوب:</span>
                                                    <span v-html="getFormattedQuantity(item.originalQuantity, item.unit, item.units_per_box)"></span>
                                                </div>
                                                <div v-if="item.sentQuantity !== null" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded-lg border border-green-100 flex items-center gap-1 font-bold">
                                                    <span>مرسل:</span>
                                                    <span v-html="getFormattedQuantity(item.sentQuantity, item.unit, item.units_per_box)"></span>
                                                </div>
                                            </div>

                                            <!-- Batch & Expiry Display -->
                                            <div class="flex items-center gap-3 mt-3">
                                                <div v-if="item.batchNumber" class="bg-amber-50 text-amber-700 px-3 py-1.5 rounded-xl border border-amber-200 flex items-center gap-2">
                                                    <div class="p-1 bg-amber-200/50 rounded-lg">
                                                        <Icon icon="solar:tag-bold" class="w-4 h-4" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] uppercase font-bold text-amber-600 leading-none mb-0.5">رقم الدفعة</span>
                                                        <span class="text-sm font-black">{{ item.batchNumber }}</span>
                                                    </div>
                                                </div>
                                                
                                                <div v-if="item.expiryDate" class="bg-purple-50 text-purple-700 px-3 py-1.5 rounded-xl border border-purple-200 flex items-center gap-2">
                                                    <div class="p-1 bg-purple-200/50 rounded-lg">
                                                        <Icon icon="solar:calendar-date-bold" class="w-4 h-4" />
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-[10px] uppercase font-bold text-purple-600 leading-none mb-0.5">تاريخ الانتهاء</span>
                                                        <span class="text-sm font-black">{{ formatDate(item.expiryDate) }}</span>
                                                    </div>
                                                </div>

                                                <div v-if="!item.batchNumber && !item.expiryDate" class="text-xs text-gray-400 italic flex items-center gap-1">
                                                    <Icon icon="solar:info-circle-linear" class="w-4 h-4" />
                                                    لا توجد بيانات للدفعة أو تاريخ الانتهاء
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col items-end gap-2 w-full md:w-auto">
                                        <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-xl border border-gray-200">
                                            <template v-if="item.units_per_box > 1">
                                                <div class="flex flex-col">
                                                    <div class="flex items-center">
                                                        <span class="text-sm text-gray-600 font-bold px-2">عدد {{ item.unit === 'مل' ? 'العبوات' : 'العلب' }}:</span>
                                                        <div class="flex items-center bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#4DA1A9] transition-all">
                                                            <input
                                                                type="number"
                                                                v-model.number="item.receivedBoxes"
                                                                class="w-20 h-10 text-center outline-none font-bold text-[#2E5077] text-lg"
                                                                @input="handleBoxInput(index)"
                                                                :disabled="props.isLoading || isConfirming"
                                                            />
                                                            <span class="px-2 bg-gray-50 text-xs text-gray-500 font-bold border-r border-gray-200 h-10 flex items-center">
                                                                {{ item.unit === 'مل' ? 'عبوة' : 'علبة' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="text-[11px] text-gray-400 mt-1 mr-2 font-medium">
                                                        الإجمالي: {{ item.receivedQuantity }} {{ item.unit }}
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <span class="text-sm text-gray-600 font-bold px-2">الكمية المستلمة:</span>
                                                <div class="flex items-center bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:border-[#4DA1A9] transition-all">
                                                    <input
                                                        type="number"
                                                        v-model.number="item.receivedQuantity"
                                                        :max="item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity"
                                                        min="0"
                                                        class="w-24 h-10 text-center outline-none font-bold text-[#2E5077] text-lg"
                                                        @input="validateQuantity(index, item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity)"
                                                        :disabled="props.isLoading || isConfirming"
                                                    />
                                                    <span class="px-2 bg-gray-50 text-xs text-gray-500 font-bold border-r border-gray-200 h-10 flex items-center">
                                                        {{ item.unit }}
                                                    </span>
                                                </div>
                                            </template>
                                        </div>
                                        
                                        <div 
                                            class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold"
                                            :class="{
                                                'bg-green-100 text-green-700': item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                'bg-amber-100 text-amber-700': item.receivedQuantity > 0 && item.receivedQuantity < (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                'bg-red-100 text-red-700': item.receivedQuantity === 0
                                            }"
                                        >
                                            <Icon v-if="item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity)" icon="solar:check-circle-bold" class="w-4 h-4" />
                                            <Icon v-else-if="item.receivedQuantity > 0" icon="solar:danger-circle-bold" class="w-4 h-4" />
                                            <Icon v-else icon="solar:close-circle-bold" class="w-4 h-4" />
                                            {{ item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity) ? 'استلام كامل' : (item.receivedQuantity > 0 ? 'استلام جزئي' : 'لم يتم الاستلام') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-gray-500">
                                لا توجد أصناف في هذا الطلب.
                            </div>
                        </div>
                    </div>

                    <!-- Notes Container -->
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            ملاحظات الاستلام
                            <span v-if="isShortageDetected" class="text-red-500 font-bold">* (إجباري لوجود نقص)</span>
                            <span v-else class="text-sm font-normal text-gray-400">(اختياري)</span>
                        </h3>
                        <div class="relative">
                            <textarea
                                v-model="notes"
                                rows="3"
                                placeholder="أدخل أي ملاحظات حول الاستلام (مثل: نقص في الكمية، تغليف تالف، إلخ)..."
                                class="w-full p-4 bg-white border rounded-xl text-gray-700 focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                                :class="[
                                    (props.isLoading || isConfirming) ? 'bg-gray-50' : 'bg-white',
                                    noteError ? 'border-red-500 focus:border-red-500' : 'border-gray-200 focus:border-[#4DA1A9]'
                                ]"
                                :disabled="props.isLoading || isConfirming"
                                @input="noteError = false"
                            ></textarea>
                            <p v-if="noteError" class="text-red-500 text-xs mt-1">يجب إدخال ملاحظات التبرير عند وجود نقص في الكمية المستلمة.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                    :disabled="props.isLoading || isConfirming"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmReceipt"
                    :disabled="props.isLoading || isConfirming"
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200"
                    :class="(props.isLoading || isConfirming) ? 'bg-gray-300 cursor-not-allowed shadow-none' : 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:bg-[#3a8c94] hover:-translate-y-0.5'"
                >
                    <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                    <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
                    {{ isConfirming ? 'جاري التأكيد...' : 'تأكيد الاستلام' }}
                </button>
            </div>

            <!-- Error Alert Notification -->
            <Transition
                enter-active-class="transition duration-300 ease-out transform"
                enter-from-class="-translate-y-full opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in transform"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-full opacity-0"
            >
                <div 
                    v-if="localAlert.show" 
                    class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] px-6 py-3 bg-[#3a8c94] text-white rounded-2xl shadow-2xl flex items-center gap-3 border border-white/20 backdrop-blur-md"
                >
                    <Icon icon="solar:danger-triangle-bold-duotone" class="w-6 h-6 text-white" />
                    <span class="font-bold">{{ localAlert.message }}</span>
                </div>
            </Transition>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
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
const noteError = ref(false);
const isConfirming = ref(false);

const localAlert = ref({
    show: false,
    message: ''
});

const showLocalAlert = (msg) => {
    localAlert.value.message = msg;
    localAlert.value.show = true;
    setTimeout(() => {
        localAlert.value.show = false;
    }, 5000);
};

// اكتشاف وجود نقص في الكميات المستلمة مقارنة بالمرسلة
const isShortageDetected = computed(() => {
    return receivedItems.value.some(item => {
        const sent = Number((item.sentQuantity !== null && item.sentQuantity !== undefined) ? item.sentQuantity : (item.originalQuantity || 0));
        const received = Number(item.receivedQuantity || 0);
        return received < sent;
    });
});

// دالة مساعدة لتنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
     
        return `${day}/${month}/${year} `;
    } catch {
        return dateString;
    }
};

// تهيئة receivedItems
watch(() => props.requestData.items, (newItems) => {
    if (newItems && newItems.length > 0) {
        receivedItems.value = newItems.map(item => {
            const requestedQty = Number(item.requested_qty || item.requestedQty || item.quantity || 0);
            let sentQty = 0;
            
            if (item.approved_qty !== null && item.approved_qty !== undefined) sentQty = Number(item.approved_qty);
            else if (item.approvedQty !== null && item.approvedQty !== undefined) sentQty = Number(item.approvedQty);
            else if (item.sentQuantity !== null && item.sentQuantity !== undefined) sentQty = Number(item.sentQuantity);
            
            let receivedQty = item.fulfilled_qty ?? item.fulfilledQty ?? item.receivedQuantity ?? sentQty;
            
            const upb = Number(item.units_per_box || item.unitsPerBox || 1);
            return {
                id: item.id || item.drugId,
                name: item.name || item.drugName || 'دواء غير محدد',
                originalQuantity: requestedQty,
                sentQuantity: sentQty,
                receivedQuantity: Number(receivedQty),
                receivedBoxes: Math.floor(Number(receivedQty) / upb),
                units_per_box: upb,
                // تعيين رقم الدفعة تلقائياً بناءً على رقم الشحنة إذا لم يكن محدداً
                batchNumber: item.batchNumber || item.batch_number || props.requestData.shipmentNumber || (props.requestData.id ? `RE-${props.requestData.id}` : null),
                expiryDate: item.expiryDate || item.expiry_date || null,
                unit: item.unit || 'وحدة'
            };
        });
        notes.value = '';
    }
}, { immediate: true, deep: true });

const handleBoxInput = (index) => {
    const item = receivedItems.value[index];
    const upb = Number(item.units_per_box || 1);
    const boxes = Number(item.receivedBoxes || 0);
    
    item.receivedQuantity = boxes * upb;
    
    const maxQty = item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity;
    validateQuantity(index, maxQty);
};

const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].receivedQuantity;
    if (isNaN(value) || value === null) value = 0;
    if (value > maxQuantity) value = maxQuantity;
    if (value < 0) value = 0;
    
    const item = receivedItems.value[index];
    item.receivedQuantity = Math.floor(value);
    
    // مزامنة العلب عند إدخال الوحدات يدوياً (إن حدث)
    if (item.units_per_box > 1) {
        item.receivedBoxes = Math.floor(item.receivedQuantity / item.units_per_box);
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

const confirmReceipt = async () => {
    const hasInvalidQuantity = receivedItems.value.some(item => 
        item.receivedQuantity === null || item.receivedQuantity === undefined || item.receivedQuantity < 0
    );
    
    if (hasInvalidQuantity) {
        showLocalAlert('يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف.');
        return;
    }

    if (isShortageDetected.value && !notes.value.trim()) {
        noteError.value = true;
        showLocalAlert('يجب إدخال ملاحظات الاستلام لتوضيح سبب النقص في الكمية.');
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
                batchNumber: item.batchNumber,
                expiryDate: item.expiryDate,
                unit: item.unit
            })),
            notes: notes.value.trim()
        };
        emit('confirm', confirmationData);
    } catch (error) {
        console.error('Error confirming receipt:', error);
        showLocalAlert('حدث خطأ أثناء تأكيد الاستلام. يرجى المحاولة مرة أخرى.');
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