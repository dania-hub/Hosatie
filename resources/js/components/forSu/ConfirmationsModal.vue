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
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الشحنة المستلمة
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">رقم الشحنة</span>
                            <span class="font-bold text-[#2E5077] font-mono text-lg">{{ requestData.shipmentNumber || requestData.id || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData.created_at || requestData.createdAt || requestData.date || requestData.requestDate || requestData.request_date) || "غير محدد" }}</span>
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
                                                مطلوب: <span v-html="getFormattedQuantity(item.originalQuantity, item.unit, item.units_per_box)"></span>
                                            </div>
                                            <div class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-xs font-bold border border-green-100 flex items-center gap-2">
                                                <Icon icon="solar:box-minimalistic-bold" class="w-3.5 h-3.5" />
                                                مرسل: <span v-html="getFormattedQuantity(item.sentQuantity, item.unit, item.units_per_box)"></span>
                                            </div>
                                            <div class="px-3 py-1.5 bg-purple-50 text-purple-600 rounded-xl text-xs font-bold border border-purple-100 flex items-center gap-2">
                                                <Icon icon="solar:check-circle-bold" class="w-3.5 h-3.5" />
                                                مستلم: <span v-html="getFormattedQuantity(getTotalReceivedForItem(item), item.unit, item.units_per_box)"></span>
                                            </div>
                                        </div>
                                        
                                    </div>

                                    <!-- Status Indicator & Batch & Expiry Fields -->
                                    <div class="flex flex-col gap-4 w-full lg:w-auto items-end">
                                        <!-- Status Indicator -->
                                        <div class="flex items-center gap-4 bg-slate-50 p-2 rounded-2xl border border-slate-200 justify-end w-full lg:w-auto">
                                            <div 
                                                class="w-6 h-5 rounded-xl flex items-center justify-center transition-all duration-300 shadow-sm"
                                                :class="{
                                                    'bg-green-100 text-green-600 shadow-green-200/50': getTotalReceivedForItem(item) >= (item.sentQuantity || item.originalQuantity),
                                                    'bg-amber-100 text-amber-600 shadow-amber-200/50': getTotalReceivedForItem(item) > 0 && getTotalReceivedForItem(item) < (item.sentQuantity || item.originalQuantity),
                                                    'bg-red-100 text-red-600 shadow-red-200/50': getTotalReceivedForItem(item) === 0
                                                }"
                                            >
                                                <Icon v-if="getTotalReceivedForItem(item) >= (item.sentQuantity || item.originalQuantity)" icon="solar:check-circle-bold" class="w-7 h-7" />
                                                <Icon v-else-if="getTotalReceivedForItem(item) > 0" icon="solar:danger-circle-bold" class="w-7 h-7" />
                                                <Icon v-else icon="solar:close-circle-bold" class="w-7 h-7" />
                                            </div>
                                        </div>

                                        <!-- Batch & Expiry Fields (Multiple Dates Support) -->
                                        <div class="flex flex-col gap-3 w-full lg:w-[400px]">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-xs font-bold text-slate-600">تواريخ انتهاء الصلاحية:</span>
                                                <button
                                                    v-if="getTotalReceivedForItem(item) < item.sentQuantity"
                                                    @click="addExpiryDate(index)"
                                                    type="button"
                                                    class="px-3 py-1 text-xs bg-[#4DA1A9] text-white rounded-lg hover:bg-[#3a8c94] transition-colors flex items-center gap-1"
                                                    :disabled="props.isLoading || isConfirming"
                                                >
                                                    <Icon icon="solar:add-circle-bold" class="w-4 h-4" />
                                                    إضافة تاريخ
                                                </button>
                                            </div>
                                            
                                            <div v-for="(expiryEntry, expiryIndex) in item.expiryDates" :key="expiryIndex" class="flex flex-col gap-2 p-3 bg-white rounded-xl border border-purple-100 shadow-sm">
                                                <div class="flex items-center gap-2">
                                                    <!-- Batch Number -->
                                                    <div class="flex-1 flex items-center gap-2 bg-amber-50 p-2 rounded-lg border border-amber-100">
                                                        <Icon icon="solar:tag-bold-duotone" class="w-4 h-4 text-amber-600" />
                                                        <input
                                                            v-model="expiryEntry.batchNumber"
                                                            placeholder="رقم الدفعة"
                                                            class="bg-transparent border-none text-xs focus:ring-0 w-full text-amber-800 placeholder:text-amber-300 font-bold"
                                                            dir="ltr"
                                                            :disabled="props.isLoading || isConfirming"
                                                        />
                                                    </div>
                                                    
                                                    <!-- Expiry Date -->
                                                    <div 
                                                        class="flex-1 flex items-center gap-2 p-2 rounded-lg border transition-colors relative"
                                                        :class="expiryEntry.expiryDate ? 'bg-purple-50 border-purple-100 cursor-pointer hover:bg-purple-100/50' : 'bg-red-50 border-red-200'"
                                                        @click="$event.currentTarget.querySelector('input').showPicker()"
                                                    >
                                                        <Icon icon="solar:calendar-bold-duotone" class="w-4 h-4" :class="expiryEntry.expiryDate ? 'text-purple-600' : 'text-red-500'" />
                                                        <input
                                                            type="date"
                                                            v-model="expiryEntry.expiryDate"
                                                            required
                                                            class="bg-transparent border-none text-xs focus:ring-0 w-full cursor-pointer font-bold"
                                                            :class="expiryEntry.expiryDate ? 'text-purple-800' : 'text-red-700'"
                                                            dir="ltr"
                                                            :disabled="props.isLoading || isConfirming"
                                                            @click.stop
                                                        />
                                                        <Icon v-if="!expiryEntry.expiryDate" icon="solar:close-circle-bold" class="w-3 h-3 text-red-500 flex-shrink-0" />
                                                    </div>
                                                    
                                                    <!-- Delete Button -->
                                                    <button
                                                        v-if="item.expiryDates.length > 1"
                                                        @click="removeExpiryDate(index, expiryIndex)"
                                                        type="button"
                                                        class="p-1 text-red-500 hover:bg-red-50 rounded-lg transition-colors flex items-center justify-center"
                                                        :disabled="props.isLoading || isConfirming"
                                                    >
                                                        <Icon icon="solar:close-circle-bold" class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                
                                                <!-- Quantity for this expiry date -->
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-bold text-slate-600 min-w-[80px]">الكمية:</span>
                                                    <div class="flex-1 flex items-center bg-slate-50 border border-slate-200 rounded-lg overflow-hidden">
                                                        <template v-if="item.units_per_box > 1">
                                                            <button 
                                                                @click="decrementExpiryBoxes(index, expiryIndex)"
                                                                class="w-10 h-8 flex items-center justify-center hover:bg-slate-200 text-slate-500 hover:text-red-500 transition-colors border-l border-slate-200"
                                                                :disabled="expiryEntry.quantity <= 0 || props.isLoading || isConfirming"
                                                                type="button"
                                                            >
                                                                <Icon icon="solar:minus-bold" class="w-4 h-4" />
                                                            </button>
                                                            <input
                                                                type="number"
                                                                v-model.number="expiryEntry.boxes"
                                                                @blur="validateExpiryBoxesInput(index, expiryIndex)"
                                                                class="w-14 h-8 text-center border-none focus:ring-0 font-bold text-[#2E5077] text-sm bg-transparent [appearance:textfield]"
                                                                :max="getMaxBoxesForExpiry(index, expiryIndex)"
                                                                min="0"
                                                                :disabled="props.isLoading || isConfirming"
                                                            />
                                                            <span class="text-[10px] font-bold text-slate-400 px-1 bg-white/50 h-8 flex items-center">عبوة</span>
                                                            <button 
                                                                @click="incrementExpiryBoxes(index, expiryIndex)"
                                                                class="w-10 h-8 flex items-center justify-center hover:bg-slate-200 text-slate-500 hover:text-[#4DA1A9] transition-colors border-r border-slate-200"
                                                                :disabled="getTotalReceivedForItem(item) >= item.sentQuantity || props.isLoading || isConfirming"
                                                                type="button"
                                                            >
                                                                <Icon icon="solar:add-bold" class="w-4 h-4" />
                                                            </button>
                                                        </template>
                                                        <template v-else>
                                                            <button 
                                                                @click="decrementExpiryQuantity(index, expiryIndex)"
                                                                class="w-10 h-8 flex items-center justify-center hover:bg-slate-200 text-slate-500 hover:text-red-500 transition-colors border-l border-slate-200"
                                                                :disabled="expiryEntry.quantity <= 0 || props.isLoading || isConfirming"
                                                                type="button"
                                                            >
                                                                <Icon icon="solar:minus-bold" class="w-4 h-4" />
                                                            </button>
                                                            <input
                                                                type="number"
                                                                v-model.number="expiryEntry.quantity"
                                                                @blur="validateExpiryQuantityInput(index, expiryIndex)"
                                                                class="w-14 h-8 text-center border-none focus:ring-0 font-bold text-[#2E5077] text-sm bg-transparent [appearance:textfield]"
                                                                :max="getMaxQuantityForExpiry(index, expiryIndex)"
                                                                min="0"
                                                                :disabled="props.isLoading || isConfirming"
                                                            />
                                                            <span class="text-[10px] font-bold text-slate-400 px-1 bg-white/50 h-8 flex items-center">{{ item.unit }}</span>
                                                            <button 
                                                                @click="incrementExpiryQuantity(index, expiryIndex)"
                                                                class="w-10 h-8 flex items-center justify-center hover:bg-slate-200 text-slate-500 hover:text-[#4DA1A9] transition-colors border-r border-slate-200"
                                                                :disabled="getTotalReceivedForItem(item) >= item.sentQuantity || props.isLoading || isConfirming"
                                                                type="button"
                                                            >
                                                                <Icon icon="solar:add-bold" class="w-4 h-4" />
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
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
            <div class="bg-slate-50/80 backdrop-blur-md px-8 py-5 flex justify-between gap-3 border-t border-slate-200 sticky bottom-0 z-20">
                <button 
                    @click="printConfirmation" 
                    class="px-6 py-2.5 rounded-xl text-white font-bold bg-[#4DA1A9] hover:bg-[#3a8c94] transition-all duration-200 active:scale-95 flex items-center gap-2"
                    :disabled="props.isLoading || isConfirming"
                >
                    <Icon icon="solar:printer-bold" class="w-5 h-5" />
                    طباعة
                </button>
                <div class="flex gap-3">
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
        const received = getTotalReceivedForItem(item);
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
            
            const upb = Number(item.units_per_box || 1);
            // تهيئة قائمة تواريخ انتهاء الصلاحية (قائمة واحدة على الأقل)
            const expiryDates = [];
            if (item.expiryDates && Array.isArray(item.expiryDates) && item.expiryDates.length > 0) {
                // إذا كانت هناك تواريخ موجودة مسبقاً، استخدمها
                expiryDates.push(...item.expiryDates.map(ed => ({
                    batchNumber: ed.batchNumber || ed.batch_number || props.requestData.shipmentNumber || (props.requestData.id ? `RE-${props.requestData.id}` : null),
                    expiryDate: ed.expiryDate || ed.expiry_date || null,
                    quantity: Number(ed.quantity || 0),
                    boxes: Math.floor(Number(ed.quantity || 0) / upb)
                })));
            } else {
                // إضافة تاريخ واحد افتراضي
                expiryDates.push({
                    batchNumber: item.batchNumber || item.batch_number || props.requestData.shipmentNumber || (props.requestData.id ? `RE-${props.requestData.id}` : null),
                    expiryDate: item.expiryDate || item.expiry_date || null,
                    quantity: 0,
                    boxes: 0
                });
            }
            
            return {
                id: item.id || item.drugId,
                name: item.name || item.drugName || 'دواء غير محدد',
                originalQuantity: requestedQty,
                sentQuantity: sentQty,
                receivedQuantity: receivedQty,
                receivedBoxes: Math.floor(receivedQty / upb),
                units_per_box: upb,
                expiryDates: expiryDates,
                unit: item.unit || 'وحدة',
                availableQuantity: item.availableQuantity ?? item.available_quantity ?? item.stock ?? item.currentStock ?? 0 // الكمية المتوفرة في المخزون
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

const incrementBoxes = (index) => {
    const item = receivedItems.value[index];
    const upb = Number(item.units_per_box || 1);
    const max = item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity;
    const currentQty = item.receivedBoxes * upb;
    
    if (currentQty + upb <= max) {
        item.receivedBoxes++;
        item.receivedQuantity = item.receivedBoxes * upb;
    }
};

const decrementBoxes = (index) => {
    const item = receivedItems.value[index];
    const upb = Number(item.units_per_box || 1);
    
    if (item.receivedBoxes > 0) {
        item.receivedBoxes--;
        item.receivedQuantity = item.receivedBoxes * upb;
    }
};

const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].receivedQuantity;
    if (isNaN(value) || value === null) value = 0;
    if (value > maxQuantity) value = maxQuantity;
    if (value < 0) value = 0;
    receivedItems.value[index].receivedQuantity = value;

    // مزامنة العلب
    const item = receivedItems.value[index];
    item.receivedBoxes = Math.floor(item.receivedQuantity / (item.units_per_box || 1));
};

const handleBoxInput = (index) => {
    const item = receivedItems.value[index];
    const upb = Number(item.units_per_box || 1);
    const boxes = Number(item.receivedBoxes || 0);
    
    item.receivedQuantity = boxes * upb;
    validateQuantity(index, item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity);
};

// دوال التعامل مع تواريخ انتهاء الصلاحية المتعددة
const addExpiryDate = (itemIndex) => {
    const item = receivedItems.value[itemIndex];
    const upb = Number(item.units_per_box || 1);
    const defaultBatch = props.requestData.shipmentNumber || (props.requestData.id ? `RE-${props.requestData.id}` : null);
    
    item.expiryDates.push({
        batchNumber: defaultBatch,
        expiryDate: null,
        quantity: 0,
        boxes: 0
    });
};

const removeExpiryDate = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    if (item.expiryDates.length > 1) {
        const removedEntry = item.expiryDates.splice(expiryIndex, 1)[0];
        // تحديث الكمية الإجمالية المستلمة
        updateItemTotalReceived(itemIndex);
    }
};

const updateExpiryQuantity = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    const upb = Number(item.units_per_box || 1);
    
    if (upb > 1) {
        // تحديث الكمية بناءً على عدد العلب
        expiryEntry.quantity = expiryEntry.boxes * upb;
    }
    
    // التحقق من عدم تجاوز الحد الأقصى
    const maxQty = getMaxQuantityForExpiry(itemIndex, expiryIndex);
    if (expiryEntry.quantity > maxQty) {
        expiryEntry.quantity = maxQty;
        if (upb > 1) {
            expiryEntry.boxes = Math.floor(maxQty / upb);
        }
    }
    
    // تحديث الكمية الإجمالية المستلمة
    updateItemTotalReceived(itemIndex);
};

const validateExpiryQuantityInput = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    const currentQty = Number(expiryEntry.quantity || 0);
    const maxQty = getMaxQuantityForExpiry(itemIndex, expiryIndex);
    
    // تصحيح القيمة إذا كانت أكبر من الحد الأقصى أو سالبة
    if (currentQty > maxQty) {
        expiryEntry.quantity = maxQty;
    } else if (currentQty < 0) {
        expiryEntry.quantity = 0;
    }
    
    // تحديث العلب إذا كان units_per_box > 1
    const upb = Number(item.units_per_box || 1);
    if (upb > 1) {
        expiryEntry.boxes = Math.floor(expiryEntry.quantity / upb);
    }
    
    // تحديث الكمية الإجمالية المستلمة
    updateItemTotalReceived(itemIndex);
};

const validateExpiryBoxesInput = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    const upb = Number(item.units_per_box || 1);
    const currentBoxes = Number(expiryEntry.boxes || 0);
    const maxBoxes = getMaxBoxesForExpiry(itemIndex, expiryIndex);
    
    // تصحيح القيمة إذا كانت أكبر من الحد الأقصى أو سالبة
    if (currentBoxes > maxBoxes) {
        expiryEntry.boxes = maxBoxes;
    } else if (currentBoxes < 0) {
        expiryEntry.boxes = 0;
    }
    
    // تحديث الكمية بناءً على عدد العلب
    expiryEntry.quantity = expiryEntry.boxes * upb;
    
    // تحديث الكمية الإجمالية المستلمة
    updateItemTotalReceived(itemIndex);
};

const incrementExpiryBoxes = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    const upb = Number(item.units_per_box || 1);
    const maxBoxes = getMaxBoxesForExpiry(itemIndex, expiryIndex);
    
    if (expiryEntry.boxes < maxBoxes) {
        expiryEntry.boxes++;
        expiryEntry.quantity = expiryEntry.boxes * upb;
        updateItemTotalReceived(itemIndex);
    }
};

const decrementExpiryBoxes = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    
    if (expiryEntry.boxes > 0) {
        expiryEntry.boxes--;
        expiryEntry.quantity = expiryEntry.boxes * Number(item.units_per_box || 1);
        updateItemTotalReceived(itemIndex);
    }
};

const incrementExpiryQuantity = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    const maxQty = getMaxQuantityForExpiry(itemIndex, expiryIndex);
    
    if (expiryEntry.quantity < maxQty) {
        expiryEntry.quantity++;
        updateItemTotalReceived(itemIndex);
    }
};

const decrementExpiryQuantity = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const expiryEntry = item.expiryDates[expiryIndex];
    
    if (expiryEntry.quantity > 0) {
        expiryEntry.quantity--;
        updateItemTotalReceived(itemIndex);
    }
};

const getMaxQuantityForExpiry = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const sentQty = item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity;
    const currentEntryQty = Number(item.expiryDates[expiryIndex].quantity || 0);
    
    // حساب الكمية الإجمالية المستلمة (باستثناء الكمية الحالية في هذا التاريخ)
    let totalReceivedWithoutCurrent = 0;
    if (item.expiryDates && Array.isArray(item.expiryDates)) {
        item.expiryDates.forEach((ed, idx) => {
            if (idx !== expiryIndex) {
                totalReceivedWithoutCurrent += Number(ed.quantity || 0);
            }
        });
    }
    
    // الكمية المتبقية من المرسلة (بما في ذلك الكمية الحالية في هذا التاريخ)
    const remainingFromSent = sentQty - totalReceivedWithoutCurrent;
    
    // الحد الأقصى هو الكمية المتبقية من المرسلة (يجب ألا تتجاوز الكمية المرسلة)
    return Math.max(0, Math.min(remainingFromSent, sentQty));
};

const getMaxBoxesForExpiry = (itemIndex, expiryIndex) => {
    const item = receivedItems.value[itemIndex];
    const upb = Number(item.units_per_box || 1);
    const maxQty = getMaxQuantityForExpiry(itemIndex, expiryIndex);
    return Math.floor(maxQty / upb);
};

const getTotalReceivedForItem = (item) => {
    if (!item.expiryDates || !Array.isArray(item.expiryDates)) {
        return item.receivedQuantity || 0;
    }
    return item.expiryDates.reduce((total, entry) => total + Number(entry.quantity || 0), 0);
};

const updateItemTotalReceived = (itemIndex) => {
    const item = receivedItems.value[itemIndex];
    item.receivedQuantity = getTotalReceivedForItem(item);
    const upb = Number(item.units_per_box || 1);
    item.receivedBoxes = Math.floor(item.receivedQuantity / upb);
};

const getFormattedQuantity = (quantity, unit = 'قرص', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = 'عبوة';

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
    const hasInvalidQuantity = receivedItems.value.some(item => {
        const totalReceived = getTotalReceivedForItem(item);
        return totalReceived === null || totalReceived === undefined || totalReceived < 0;
    });
    
    if (hasInvalidQuantity) {
        showLocalAlert('يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف.');
        return;
    }

    // التحقق من أن جميع تواريخ انتهاء الصلاحية مملوءة
    const hasMissingExpiryDate = receivedItems.value.some(item => {
        if (!item.expiryDates || !Array.isArray(item.expiryDates)) {
            return false;
        }
        return item.expiryDates.some(ed => {
            const qty = Number(ed.quantity || 0);
            return qty > 0 && !ed.expiryDate;
        });
    });

    if (hasMissingExpiryDate) {
        showLocalAlert('يجب إدخال تاريخ انتهاء الصلاحية لجميع الكميات المستلمة.');
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
                expiryDates: (item.expiryDates || []).filter(ed => ed.quantity > 0 && ed.expiryDate).map(ed => ({
                    batchNumber: ed.batchNumber,
                    expiryDate: ed.expiryDate,
                    quantity: ed.quantity
                })),
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

// دالة لتنسيق الكمية بالعبوة للطباعة (نص بدون HTML)
const getFormattedQuantityForPrint = (quantity, unit = 'وحدة', unitsPerBox = 1) => {
    const qty = Number(quantity || 0);
    const upb = Number(unitsPerBox || 1);
    const boxUnit = 'عبوة';

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
const printConfirmation = () => {
    try {
        const printWindow = window.open('', '_blank', 'height=600,width=800');
        
        if (!printWindow || printWindow.closed || typeof printWindow.closed === 'undefined') {
            console.error('فشل في فتح نافذة الطباعة. يرجى السماح بفتح النوافذ المنبثقة.');
            return;
        }
    
    // إعداد بيانات الأدوية للطباعة
    const itemsHtml = receivedItems.value.map(item => {
        const totalReceived = getTotalReceivedForItem(item);
        const sentQty = item.sentQuantity !== null && item.sentQuantity !== undefined ? item.sentQuantity : item.originalQuantity;
        const unitsPerBox = item.units_per_box || 1;
        const unit = item.unit || 'وحدة';
        
        // استخدام getFormattedQuantityForPrint لعرض الكميات بالعبوة
        const formattedRequested = getFormattedQuantityForPrint(item.originalQuantity, unit, unitsPerBox);
        const formattedSent = getFormattedQuantityForPrint(sentQty, unit, unitsPerBox);
        const formattedReceived = getFormattedQuantityForPrint(totalReceived, unit, unitsPerBox);
        
        // إعداد تواريخ انتهاء الصلاحية
        let expiryInfo = '';
        if (item.expiryDates && item.expiryDates.length > 0) {
            expiryInfo = item.expiryDates.filter(ed => ed.quantity > 0).map(ed => {
                const batchStr = ed.batchNumber ? `دفعة: ${ed.batchNumber}` : '';
                const expiryStr = ed.expiryDate ? `انتهاء: ${ed.expiryDate}` : '';
                const qtyStr = ed.quantity ? `(${getFormattedQuantityForPrint(ed.quantity, unit, unitsPerBox)})` : '';
                return [batchStr, expiryStr, qtyStr].filter(Boolean).join(' - ');
            }).join('<br>');
        }
        
        return `
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">${item.name || 'غير محدد'}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedRequested}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${formattedSent}</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center; ${totalReceived < sentQty ? 'color: #dc2626; font-weight: bold;' : 'color: #16a34a;'}">${formattedReceived}</td>
                <td style="padding: 10px; border: 1px solid #ddd; font-size: 11px;">${expiryInfo || '-'}</td>
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
                .notes-section { background-color: #f0f9ff; padding: 15px; border: 1px solid #4DA1A9; margin-top: 20px; border-radius: 5px; }
                .shortage-warning { background: #fef2f2; border: 1px solid #fca5a5; padding: 10px; border-radius: 5px; margin-top: 20px; color: #dc2626; }
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
                    <span>${props.requestData.shipmentNumber || props.requestData.id || 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">تاريخ الطلب:</span>
                    <span>${formatDate(props.requestData.created_at || props.requestData.createdAt || props.requestData.date || props.requestData.requestDate) || 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">عدد الأصناف:</span>
                    <span>${receivedItems.value.length}</span>
                </div>
            </div>

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
            
            ${isShortageDetected.value ? `
            <div class="shortage-warning">
                <strong>تنبيه:</strong> يوجد نقص في بعض الكميات المستلمة مقارنة بالكميات المرسلة.
            </div>
            ` : ''}
            
            ${notes.value ? `
            <div class="notes-section">
                <h3 style="color: #2E5077; margin-top: 0;">ملاحظات الاستلام</h3>
                <p>${notes.value}</p>
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

<style scoped>
/* إخفاء أسهم الزيادة والنقصان الافتراضية في المتصفح */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type=number] {
  -moz-appearance: textfield;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>