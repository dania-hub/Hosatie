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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[95vh] overflow-y-auto"
            dir="rtl"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-6 flex justify-between items-center relative overflow-hidden shrink-0">
                <div class="absolute top-0 right-0 w-40 h-40 bg-white/10 rounded-full -mr-20 -mt-20 blur-3xl animate-pulse"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-[#4DA1A9]/30 rounded-full -ml-16 -mb-16 blur-2xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-4 relative z-10">
                    <div class="p-3 bg-white/15 rounded-2xl backdrop-blur-md border border-white/20 shadow-inner">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-8 h-8 text-[#4DA1A9]" />
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-white/70 mb-0.5">استلام وتأكيد</span>
                        <span>الشحنة رقم {{ requestData.shipmentNumber || requestData.id || "..." }}</span>
                    </div>
                </h2>
                
                <button 
                    @click="closeModal" 
                    class="text-white/70 hover:text-white hover:bg-white/15 p-2.5 rounded-xl transition-all duration-300 relative z-10 border border-transparent hover:border-white/20"
                >
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-4 sm:p-8 space-y-8 overflow-y-auto custom-scrollbar">
                
                <!-- Shipment Info Section -->
                <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-slate-200/60 relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1 h-full bg-[#4DA1A9] transition-all duration-300 group-hover:w-1.5"></div>
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-3">
                            <div class="p-2 bg-slate-50 rounded-lg">
                                <Icon icon="solar:info-circle-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                            </div>
                            بيانات الشحنة المستلمة
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100 transition-all hover:bg-slate-50 flex flex-col gap-1">
                            <span class="text-slate-400 text-xs font-bold">رقم الشحنة</span>
                            <span class="font-bold text-[#2E5077] text-base font-mono">{{ requestData.shipmentNumber || requestData.id || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100 transition-all hover:bg-slate-50 flex flex-col gap-1">
                            <span class="text-slate-400 text-xs font-bold">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077] text-base">{{ formatDate(requestData.created_at || requestData.createdAt || requestData.date || requestData.requestDate || requestData.request_date) || "غير محدد" }}</span>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between px-2">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-3">
                            <div class="p-2 bg-slate-50 rounded-lg">
                                <Icon icon="solar:pill-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                            </div>
                            مراجعة الكميات المستلمة
                        </h3>
                        <span class="text-slate-400 text-sm font-medium">عدد الأصناف: {{ receivedItems.length }}</span>
                    </div>

                    <div class="bg-white rounded-[1.5rem] border border-slate-200/60 overflow-hidden shadow-sm">
                        <div v-if="receivedItems.length > 0" class="divide-y divide-slate-100">
                            <div 
                                v-for="(item, index) in receivedItems" 
                                :key="item.id || index"
                                class="p-6 transition-colors hover:bg-slate-50/40 relative group"
                            >
                                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                                    <!-- Item Info -->
                                    <div class="flex-1 w-full">
                                         <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center group-hover:bg-[#4DA1A9]/10 transition-colors">
                                                <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-[#2E5077] text-lg leading-tight">{{ item.name }}</h4>
                                                <div class="flex gap-2 mt-1">
                                                    <span v-if="item.unit" class="text-[10px] bg-blue-50 text-blue-500 px-2 py-0.5 rounded-md font-bold">
                                                        {{ item.unit }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-3 flex-wrap mt-2">
                                            <div class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-xs font-bold border border-blue-100 flex items-center gap-2">
                                                <Icon icon="solar:cart-large-minimalistic-bold" class="w-3.5 h-3.5" />
                                                مطلوب: {{ item.originalQuantity }}
                                            </div>
                                            <div class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-xs font-bold border border-green-100 flex items-center gap-2">
                                                <Icon icon="solar:box-minimalistic-bold" class="w-3.5 h-3.5" />
                                                مرسل: {{ item.sentQuantity }}
                                            </div>
                                        </div>
                                        
                                    </div>

                                    <!-- Action: Receive Quantity -->
                                    <div class="flex flex-col gap-4 w-full lg:w-auto items-end">
                                        <div class="flex items-center gap-4 bg-slate-50 p-2 rounded-2xl border border-slate-200 justify-between lg:justify-end w-full lg:w-auto">
                                            <label class="text-sm font-bold text-slate-500 px-2 flex items-center gap-2">
                                                <Icon icon="solar:file-check-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                                مستلم:
                                            </label>
                                            
                                            <div class="flex items-center bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                                <button 
                                                    @click="decrementQuantity(index)"
                                                    class="p-2 hover:bg-slate-50 text-slate-400 hover:text-[#4DA1A9] transition-colors"
                                                    :disabled="item.receivedQuantity <= 0 || props.isLoading || isConfirming"
                                                >
                                                    <Icon icon="solar:minus-circle-bold" class="w-6 h-6" />
                                                </button>

                                                <input
                                                    type="number"
                                                    v-model.number="item.receivedQuantity"
                                                    :max="item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity"
                                                    min="0"
                                                    class="w-16 h-10 text-center border-none focus:ring-0 font-black text-[#2E5077] text-lg [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                    :class="{
                                                        'text-green-600': item.receivedQuantity === (item.sentQuantity || item.originalQuantity),
                                                        'text-amber-500': item.receivedQuantity < (item.sentQuantity || item.originalQuantity) && item.receivedQuantity > 0,
                                                        'text-red-500': item.receivedQuantity === 0
                                                    }"
                                                    @input="validateQuantity(index, item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity)"
                                                />
                                                
                                                <button 
                                                    @click="incrementQuantity(index)"
                                                    class="p-2 hover:bg-slate-50 text-slate-400 hover:text-[#4DA1A9] transition-colors"
                                                    :disabled="item.receivedQuantity >= (item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity) || props.isLoading || isConfirming"
                                                >
                                                    <Icon icon="solar:add-circle-bold" class="w-6 h-6" />
                                                </button>
                                            </div>
                                            
                                            <div 
                                                class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 shadow-sm"
                                                :class="{
                                                    'bg-green-100 text-green-600 shadow-green-200/50': item.receivedQuantity >= (item.sentQuantity || item.originalQuantity),
                                                    'bg-amber-100 text-amber-600 shadow-amber-200/50': item.receivedQuantity > 0 && item.receivedQuantity < (item.sentQuantity || item.originalQuantity),
                                                    'bg-red-100 text-red-600 shadow-red-200/50': item.receivedQuantity === 0
                                                }"
                                            >
                                                <Icon v-if="item.receivedQuantity >= (item.sentQuantity || item.originalQuantity)" icon="solar:check-circle-bold" class="w-6 h-6" />
                                                <Icon v-else-if="item.receivedQuantity > 0" icon="solar:danger-circle-bold" class="w-6 h-6" />
                                                <Icon v-else icon="solar:close-circle-bold" class="w-6 h-6" />
                                            </div>
                                        </div>

                                        <!-- Batch & Expiry Fields (New Location) -->
                                        <div class="flex flex-col gap-3 w-[260px] lg:w-[320px] mt-4 self-end">
                                            <!-- Batch Number -->
                                            <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-amber-100 focus-within:border-amber-300 focus-within:ring-2 focus-within:ring-amber-100 transition-all shadow-sm">
                                                <div class="flex items-center gap-2 border-l border-amber-100 pl-3 min-w-[110px]">
                                                    <div class="p-1.5 bg-amber-50 rounded-lg">
                                                        <Icon icon="solar:tag-bold-duotone" class="w-5 h-5 text-amber-600" />
                                                    </div>
                                                    <span class="text-xs font-bold text-amber-900">رقم الدفعة:</span>
                                                </div>
                                                <input
                                                    v-model="item.batchNumber"
                                                    placeholder="Batch Number"
                                                    class="bg-transparent border-none text-sm focus:ring-0 w-full text-amber-800 placeholder:text-amber-300 font-bold text-left tracking-wide"
                                                    dir="ltr"
                                                    :disabled="props.isLoading || isConfirming"
                                                />
                                            </div>
                                            
                                            <!-- Expiry Date -->
                                            <div 
                                                class="flex items-center gap-3 bg-white p-3 rounded-xl border border-purple-100 focus-within:border-purple-300 focus-within:ring-2 focus-within:ring-purple-100 transition-all shadow-sm cursor-pointer hover:bg-purple-50/30"
                                                @click="$event.currentTarget.querySelector('input').showPicker()"
                                            >
                                                <div class="flex items-center gap-2 border-l border-purple-100 pl-3 min-w-[110px]">
                                                    <div class="p-1.5 bg-purple-50 rounded-lg">
                                                        <Icon icon="solar:calendar-bold-duotone" class="w-5 h-5 text-purple-600" />
                                                    </div>
                                                    <span class="text-xs font-bold text-purple-900">تاريخ إنتهاء الصلاحية:</span>
                                                </div>
                                                <input
                                                    type="date"
                                                    v-model="item.expiryDate"
                                                    class="bg-transparent border-none text-sm focus:ring-0 w-full text-purple-800 cursor-pointer font-bold text-left tracking-wide"
                                                    dir="ltr"
                                                    :disabled="props.isLoading || isConfirming"
                                                    @click.stop
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-16 text-center border-2 border-dashed border-slate-100 rounded-2xl m-6">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100 shadow-inner">
                                <Icon icon="solar:box-minimalistic-broken" class="w-10 h-10 text-slate-300" />
                            </div>
                            <p class="text-slate-500 font-bold tracking-wide">لا توجد أصناف في هذا الطلب</p>
                        </div>
                    </div>
                </div>

                <!-- Notes Container -->
                <div class="space-y-3">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        ملاحظات الاستلام
                        <span v-if="isShortageDetected" class="text-red-500 font-bold">* (إجباري لوجود نقص في الكمية)</span>
                        <span v-else class="text-sm font-normal text-gray-400">(اختياري)</span>
                    </h3>
                    <div class="relative group">
                        <textarea
                            v-model="notes"
                            rows="3"
                            placeholder="أدخل أي ملاحظات حول الاستلام (مثال: 'تم استلام الشحنة بحالة جيدة'، 'يوجد كرتون تالف'، 'نقص في العدد'.....)"
                            class="w-full p-4 bg-white border rounded-2xl text-gray-700 focus:ring-4 transition-all resize-none shadow-sm"
                            :class="[
                                (props.isLoading || isConfirming) ? 'bg-gray-50' : 'bg-white',
                                noteError ? 'border-red-500 focus:border-red-500 focus:ring-red-500/10' : 'border-slate-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/10'
                            ]"
                            :disabled="props.isLoading || isConfirming"
                            @input="noteError = false"
                        ></textarea>
                        <p v-if="noteError" class="text-red-500 text-sm mt-2 flex items-center gap-1 font-bold animate-pulse">
                            <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                            يجب إدخال ملاحظات لتبرير النقص في الكمية المستلمة.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-50/80 backdrop-blur-md px-8 py-5 flex justify-end gap-3 border-t border-slate-200 sticky bottom-0 z-20">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-200 hover:text-slate-800 transition-all duration-200 active:scale-95"
                    :disabled="props.isLoading || isConfirming"
                >
                    إلغاء الأمر
                </button>
                <button
                    @click="confirmReceipt"
                    :disabled="props.isLoading || isConfirming"
                    class="px-8 py-2.5 rounded-xl text-white font-bold shadow-lg shadow-[#4DA1A9]/20 flex items-center gap-2 transition-all duration-200 active:scale-95"
                    :class="(props.isLoading || isConfirming) ? 'bg-slate-400 cursor-not-allowed shadow-none' : 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:bg-[#3a8c94] hover:-translate-y-0.5'"
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
                    class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] px-6 py-3 bg-red-500 text-white rounded-2xl shadow-2xl flex items-center gap-3 border border-red-400/20 backdrop-blur-md"
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
    if (!dateString) return 'غير محدد';
    try {
        const date = new Date(dateString);
        // Check if date is valid
        if (isNaN(date.getTime())) return dateString;

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
     
        return `${year}-${month}-${day}`;
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
            
            // Default received quantity to 0 as requested
            let receivedQty = 0; 
            
            return {
                id: item.id || item.drugId,
                name: item.name || item.drugName || 'دواء غير محدد',
                originalQuantity: requestedQty,
                sentQuantity: sentQty,
                receivedQuantity: receivedQty,
                // تعيين رقم الدفعة تلقائياً بناءً على رقم الشحنة إذا لم يكن محدداً
                batchNumber: item.batchNumber || item.batch_number || props.requestData.shipmentNumber || (props.requestData.id ? `RE-${props.requestData.id}` : null),
                expiryDate: item.expiryDate || item.expiry_date || null,
                unit: item.unit || 'وحدة'
            };
        });
        notes.value = '';
    }
}, { immediate: true, deep: true });

const incrementQuantity = (index) => {
    const item = receivedItems.value[index];
    const max = item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity;
    if (item.receivedQuantity < max) {
        item.receivedQuantity++;
    }
};

const decrementQuantity = (index) => {
    const item = receivedItems.value[index];
    if (item.receivedQuantity > 0) {
        item.receivedQuantity--;
    }
};

const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].receivedQuantity;
    if (isNaN(value) || value === null) value = 0;
    if (value > maxQuantity) value = maxQuantity;
    if (value < 0) value = 0;
    receivedItems.value[index].receivedQuantity = value;
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