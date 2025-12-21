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
                                <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData.id || 'غير محدد' }}</span>
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
                            <Icon icon="solar:checklist-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
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
                                        <div class="font-bold text-[#2E5077] text-lg">{{ item.name }}</div>
                                        <div class="text-sm text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                                            <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md font-medium">مطلوب: {{ item.originalQuantity }} {{ item.unit }}</span>
                                            <span v-if="item.sentQuantity !== null && item.sentQuantity !== undefined && !isNaN(item.sentQuantity)" class="bg-green-50 text-green-600 px-2 py-0.5 rounded-md font-medium">مرسل: {{ item.sentQuantity }} {{ item.unit }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                                        <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-xl border border-gray-200">
                                            <span class="text-sm text-gray-500 font-medium px-2">مستلم:</span>
                                            <input
                                                type="number"
                                                v-model.number="item.receivedQuantity"
                                                :max="item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity"
                                                min="0"
                                                class="w-20 h-9 text-center bg-white border border-gray-200 rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077]"
                                                :class="{
                                                    'text-green-600': item.receivedQuantity === (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                    'text-amber-600': item.receivedQuantity > 0 && item.receivedQuantity < (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                    'text-red-600': item.receivedQuantity === 0
                                                }"
                                                @input="validateQuantity(index, item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity)"
                                                :disabled="props.isLoading || isConfirming"
                                            />
                                        </div>
                                        
                                        <div 
                                            class="w-10 h-10 rounded-full flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-green-100 text-green-600': item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                'bg-amber-100 text-amber-600': item.receivedQuantity > 0 && item.receivedQuantity < (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity),
                                                'bg-red-100 text-red-600': item.receivedQuantity === 0
                                            }"
                                        >
                                            <Icon v-if="item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity)" icon="solar:check-circle-bold" class="w-6 h-6" />
                                            <Icon v-else-if="item.receivedQuantity > 0" icon="solar:danger-circle-bold" class="w-6 h-6" />
                                            <Icon v-else icon="solar:close-circle-bold" class="w-6 h-6" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-gray-500">
                                لا توجد أصناف في هذا الطلب.
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            ملاحظات الاستلام <span class="text-sm font-normal text-gray-400">(اختياري)</span>
                        </h3>
                        <textarea
                            v-model="notes"
                            rows="3"
                            placeholder="أدخل أي ملاحظات حول الاستلام (مثل: نقص في الكمية، تغليف تالف، إلخ)..."
                            class="w-full p-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                            :disabled="props.isLoading || isConfirming"
                        ></textarea>
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
                    :class="(props.isLoading || isConfirming) ? 'bg-gray-300 cursor-not-allowed shadow-none' : 'bg-[#4DA1A9] hover:bg-[#3a8c94] hover:-translate-y-0.5'"
                >
                    <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                    <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
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
        receivedItems.value = newItems.map(item => {
            // الحصول على الكمية المطلوبة
            const requestedQty = Number(item.requested_qty || item.requestedQty || item.quantity || 0);
            
            // الحصول على الكمية المرسلة (المعتمدة) - تحقق من جميع الأسماء المحتملة
            let sentQty = null;
            
            // التحقق من approved_qty (snake_case) - أولوية عالية
            if (item.approved_qty !== null && item.approved_qty !== undefined && item.approved_qty !== '') {
                const val = Number(item.approved_qty);
                if (!isNaN(val) && val >= 0) {
                    sentQty = val;
                }
            }
            // التحقق من approvedQty (camelCase)
            else if (item.approvedQty !== null && item.approvedQty !== undefined && item.approvedQty !== '') {
                const val = Number(item.approvedQty);
                if (!isNaN(val) && val >= 0) {
                    sentQty = val;
                }
            }
            // التحقق من sentQuantity
            else if (item.sentQuantity !== null && item.sentQuantity !== undefined && item.sentQuantity !== '') {
                const val = Number(item.sentQuantity);
                if (!isNaN(val) && val >= 0) {
                    sentQty = val;
                }
            }
            // التحقق من sent
            else if (item.sent !== null && item.sent !== undefined && item.sent !== '') {
                const val = Number(item.sent);
                if (!isNaN(val) && val >= 0) {
                    sentQty = val;
                }
            }
            // التحقق من approved
            else if (item.approved !== null && item.approved !== undefined && item.approved !== '') {
                const val = Number(item.approved);
                if (!isNaN(val) && val >= 0) {
                    sentQty = val;
                }
            }
            
            // الحصول على الكمية المستلمة الفعلية - فقط fulfilled_qty، لا نستخدم approved_qty كبديل
            let receivedQty = null;
            
            // التحقق من fulfilled_qty أولاً (الكمية المستلمة الفعلية)
            if (item.fulfilled_qty !== null && item.fulfilled_qty !== undefined && item.fulfilled_qty !== '') {
                const val = Number(item.fulfilled_qty);
                if (!isNaN(val) && val >= 0) {
                    receivedQty = val;
                }
            }
            // التحقق من fulfilledQty
            else if (item.fulfilledQty !== null && item.fulfilledQty !== undefined && item.fulfilledQty !== '') {
                const val = Number(item.fulfilledQty);
                if (!isNaN(val) && val >= 0) {
                    receivedQty = val;
                }
            }
            // التحقق من fulfilled
            else if (item.fulfilled !== null && item.fulfilled !== undefined && item.fulfilled !== '') {
                const val = Number(item.fulfilled);
                if (!isNaN(val) && val >= 0) {
                    receivedQty = val;
                }
            }
            // التحقق من receivedQuantity فقط إذا لم يكن نفس approved_qty
            else if (item.receivedQuantity !== null && item.receivedQuantity !== undefined && item.receivedQuantity !== '') {
                const approved = item.approved_qty ?? item.approvedQty ?? null;
                // إذا كان receivedQuantity مختلف عن approved_qty، نستخدمه
                if (approved === null || Number(item.receivedQuantity) !== Number(approved)) {
                    const val = Number(item.receivedQuantity);
                    if (!isNaN(val) && val >= 0) {
                        receivedQty = val;
                    }
                }
            }
            
            // إذا لم توجد كمية مستلمة فعلية، نستخدم الكمية المرسلة كقيمة افتراضية للعرض فقط
            // لكن المستخدم يمكنه تعديلها
            const defaultReceivedQty = receivedQty !== null ? receivedQty : (sentQty !== null && sentQty !== undefined ? sentQty : 0);
            
            return {
                id: item.id || item.drugId,
                name: item.name || item.drugName || 'دواء غير محدد',
                originalQuantity: requestedQty,
                sentQuantity: sentQty,
                receivedQuantity: defaultReceivedQty,
                unit: item.unit || 'وحدة'
            };
        });
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