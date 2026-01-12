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
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    معالجة الشحنة رقم {{ requestData.shipmentNumber || "..." }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
                <!-- Shipment Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:info-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الشحنة
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الجهة الطالبة</span>
                            <span class="font-bold text-[#2E5077]">{{ requestData.department || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">تاريخ الطلب</span>
                            <span class="font-bold text-[#2E5077]">{{ formatDate(requestData.date) || "غير محدد" }}</span>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-xl flex justify-between items-center">
                            <span class="text-gray-500 font-medium">الحالة</span>
                            <span class="inline-block px-3 py-1 rounded-lg text-sm font-bold bg-orange-100 text-orange-600">
                                {{ requestData.status || "قيد الاستلام" }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:checklist-minimalistic-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        <span v-if="isProcessing">الكميات المستلمة</span>
                        <span v-else>الأدوية المطلوبة والمخزون المتاح</span>
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div v-if="receivedItems.length > 0" class="divide-y divide-gray-50">
                            <div 
                                v-for="(item, index) in receivedItems" 
                                :key="item.id || index"
                                class="p-5 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <!-- Item Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-[#2E5077] text-lg">{{ item.name }}</h4>
                                            <span v-if="item.dosage" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md font-medium">
                                                {{ item.dosage }}
                                            </span>
                                        </div>
                                        
                                        <div class="text-sm text-gray-500 mt-1 flex items-center gap-2 flex-wrap">
                                            <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md font-medium">مطلوب: {{ item.originalQuantity }} {{ item.unit }}</span>
                                            <span v-if="item.sentQuantity !== null && item.sentQuantity !== undefined && !isNaN(item.sentQuantity)" class="bg-green-50 text-green-600 px-2 py-0.5 rounded-md font-medium">مرسل: {{ item.sentQuantity }} {{ item.unit }}</span>
                                            
                                            <div 
                                                v-if="!isProcessing"
                                                class="flex items-center gap-1 px-2 py-0.5 rounded-md border"
                                                :class="{
                                                    'bg-green-50 border-green-100 text-green-700': item.availableQuantity >= item.originalQuantity,
                                                    'bg-red-50 border-red-100 text-red-700': item.availableQuantity < item.originalQuantity
                                                }"
                                            >
                                                <span class="font-medium text-xs">متوفر:</span>
                                                <span class="font-bold text-xs">{{ item.availableQuantity }} {{ item.unit }}</span>
                                                <Icon v-if="item.availableQuantity < item.originalQuantity" icon="solar:danger-circle-bold" class="w-3 h-3" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex flex-col md:flex-row items-start md:items-center gap-3 w-full md:w-auto">
                                        <!-- عند تأكيد الاستلام: عرض حقل "مستلم" فقط -->
                                        <template v-if="isProcessing">
                                            <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                                                <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-xl border border-gray-200">
                                                    <span class="text-sm text-gray-500 font-medium px-2">مستلم:</span>
                                                    <input
                                                        type="number"
                                                        v-model.number="item.receivedQuantity"
                                                        :max="item.sentQuantity"
                                                        :min="0"
                                                        class="w-20 h-9 text-center bg-white border border-gray-200 rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077]"
                                                        :class="{
                                                            'text-green-600': item.receivedQuantity === item.sentQuantity && item.sentQuantity > 0,
                                                            'text-amber-600': item.receivedQuantity > 0 && item.receivedQuantity < item.sentQuantity,
                                                            'text-red-600': item.receivedQuantity === 0 || item.sentQuantity === 0,
                                                            'bg-gray-100 cursor-not-allowed opacity-70': item.sentQuantity === 0
                                                        }"
                                                        @input="validateReceivedQuantity(index, item.sentQuantity)"
                                                        :disabled="props.isLoading || isConfirming || item.sentQuantity === 0"
                                                    />
                                                </div>
                                                
                                                <div 
                                                    class="w-10 h-10 rounded-full flex items-center justify-center transition-colors"
                                                    :class="{
                                                        'bg-green-100 text-green-600': item.receivedQuantity === item.sentQuantity && item.sentQuantity > 0,
                                                        'bg-amber-100 text-amber-600': item.receivedQuantity > 0 && item.receivedQuantity < item.sentQuantity,
                                                        'bg-red-100 text-red-600': item.receivedQuantity === 0 || item.sentQuantity === 0
                                                    }"
                                                >
                                                    <Icon v-if="item.receivedQuantity === item.sentQuantity && item.sentQuantity > 0" icon="solar:check-circle-bold" class="w-6 h-6" />
                                                    <Icon v-else-if="item.receivedQuantity > 0" icon="solar:danger-circle-bold" class="w-6 h-6" />
                                                    <Icon v-else icon="solar:close-circle-bold" class="w-6 h-6" />
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <!-- عند الإرسال: عرض الكمية المقترحة والمرسلة -->
                                        <template v-else>
                                            <!-- الكمية المقترجة -->
                                            <div class="flex items-center gap-2 bg-blue-50 p-2 rounded-xl border border-blue-200">
                                                <label class="text-sm font-bold text-blue-600 px-2">
                                                    الكمية المقترحة:
                                                </label>
                                                <div class="w-24 h-10 flex items-center justify-center bg-white border border-blue-300 rounded-lg font-bold text-blue-700 text-lg">
                                                    {{ item.suggestedQuantity || 0 }}
                                                </div>
                                            </div>
                                            
                                            <!-- الكمية المرسلة -->
                                            <div class="flex items-center gap-3 bg-gray-50 p-2 rounded-xl border border-gray-200">
                                                <label :for="`sent-qty-${index}`" class="text-sm font-bold text-gray-500 px-2">
                                                    الكمية المرسلة:
                                                </label>
                                                <input
                                                    :id="`sent-qty-${index}`"
                                                    type="number"
                                                    v-model.number="item.sentQuantity"
                                                    :max="Math.min(item.availableQuantity, item.originalQuantity)"
                                                    :min="0"
                                                    class="w-24 h-10 text-center bg-white border rounded-lg focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077] text-lg"
                                                    :class="{
                                                        'border-red-300 focus:border-red-500': item.sentQuantity > Math.min(item.availableQuantity, item.originalQuantity),
                                                        'border-green-300 focus:border-green-500': item.sentQuantity <= Math.min(item.availableQuantity, item.originalQuantity) && item.sentQuantity > 0,
                                                        'border-gray-200 focus:border-[#4DA1A9]': item.sentQuantity === 0
                                                    }"
                                                    @input="validateQuantity(index, Math.min(item.availableQuantity, item.originalQuantity))"
                                                    :disabled="props.isLoading || isConfirming"
                                                />
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-gray-500">
                            <Icon icon="solar:box-minimalistic-broken" class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            لا توجد أدوية في هذا الطلب
                        </div>
                    </div>
                </div>

                <!-- Rejection Section -->
                <div v-if="showRejectionNote" class="bg-red-50 border border-red-100 rounded-2xl p-6 animate-in fade-in slide-in-from-top-4 duration-300">
                    <h4 class="text-lg font-bold text-red-700 mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6" />
                        سبب رفض الطلب
                    </h4>
                    
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-red-800">
                            يرجى كتابة سبب الرفض (إلزامي) <span class="text-red-600">*</span>
                        </label>
                        
                    <textarea
                        v-model="rejectionNote"
                        placeholder="مثال: نقص في المخزون - طلب غير مطابق للسياسات - بيانات ناقصة..."
                        rows="3"
                        class="w-full p-4 border-2 rounded-xl bg-white text-gray-800 transition-all duration-200 resize-none focus:outline-none focus:ring-4 focus:ring-red-500/10"
                        :class="{
                            'border-red-500 focus:border-red-500': rejectionError,
                            'border-red-200 focus:border-red-400': !rejectionError
                        }"
                        @input="rejectionError = false"
                    ></textarea>
                        
                        <div v-if="rejectionError" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                            <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                            يجب كتابة سبب الرفض قبل الإرسال
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div v-else class="space-y-2">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        <span v-if="isProcessing">ملاحظات الاستلام</span>
                        <span v-else>ملاحظات الإرسال</span>
                        <span v-if="(isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)" class="text-sm font-normal text-red-600">* (إجباري لوجود نقص)</span>
                        <span v-else class="text-sm font-normal text-gray-400">(اختياري)</span>
                    </h3>
                    <textarea
                        v-model="additionalNotes"
                        :placeholder="(isProcessing && hasShortage) ? 'يجب إضافة ملاحظات عند وجود نقص في الكميات المستلمة...' : (!isProcessing && hasZeroQuantityItem) ? 'يجب إضافة ملاحظات عند وجود عنصر بكمية مرسلة = 0...' : 'أضف أي ملاحظات حول الشحنة...'"
                        rows="2"
                        class="w-full p-4 bg-white border rounded-xl text-gray-700 focus:ring-2 transition-all resize-none"
                        :class="{
                            'bg-gray-100': isProcessing && !hasShortage,
                            'border-red-500 focus:border-red-500 focus:ring-red-500/20': ((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)) && notesError,
                            'border-orange-300 focus:border-orange-500 focus:ring-orange-500/20': ((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem)) && !notesError,
                            'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !((isProcessing && hasShortage) || (!isProcessing && hasZeroQuantityItem))
                        }"
                        @input="notesError = false"
                    ></textarea>
                    <div v-if="(isProcessing && hasShortage && notesError)" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                        <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                        يجب إضافة ملاحظات الاستلام عند وجود نقص في الكميات المستلمة
                    </div>
                    <div v-if="(!isProcessing && hasZeroQuantityItem && notesError)" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                        <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                        يجب إضافة ملاحظات الإرسال عند وجود عنصر بكمية مرسلة = 0
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex flex-col-reverse sm:flex-row justify-between gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                    :disabled="props.isLoading || isConfirming"
                >
                    إلغاء
                </button>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <!-- Rejection Actions -->
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            إلغاء الرفض
                        </button>
                        
                        <button
                            @click="confirmRejection"
                            class="px-6 py-2.5 rounded-xl bg-red-500 text-white font-medium hover:bg-red-600 transition-colors duration-200 shadow-lg shadow-red-500/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:close-circle-bold" class="w-5 h-5" />
                            تأكيد الرفض
                        </button>
                    </template>

                    <!-- Normal Actions -->
                    <template v-else>
                        <!-- عند تأكيد الاستلام -->
                        <template v-if="isProcessing">
                            <button
                                @click="confirmReceipt"
                                class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :disabled="props.isLoading || isConfirming"
                            >
                                <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
                                {{ isConfirming ? "جاري التأكيد..." : "تأكيد الاستلام" }}
                            </button>
                        </template>
                        
                        <!-- عند الإرسال -->
                        <template v-else>
                            <button
                                @click="initiateRejection"
                                class="px-6 py-2.5 rounded-xl text-red-500 bg-red-50 border border-red-100 font-medium hover:bg-red-100 transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :disabled="props.isLoading || isConfirming"
                            >
                                <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                رفض الطلب
                            </button>

                            <div class="relative w-full sm:w-auto">
                                <span v-if="isAllItemsZero" class="absolute -top-10 left-1/2 transform -translate-x-1/2 w-max px-3 py-1.5 bg-red-50 text-red-600 text-xs font-bold rounded-lg border border-red-100 shadow-md animate-in fade-in slide-in-from-bottom-2">
                                    يرجى إدخال الكمية المرسلة أولاً
                                    <div class="absolute -bottom-1 left-1/2 -ml-1 w-2 h-2 bg-red-50 border-b border-l border-red-100 transform -rotate-45"></div>
                                </span>
                                
                                <button
                                    @click="sendShipment"
                                    class="w-full px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2"
                                    :class="{
                                        'opacity-50 cursor-not-allowed grayscale': isAllItemsZero,
                                        'hover:bg-[#3a8c94] hover:shadow-xl hover:-translate-y-0.5': !isAllItemsZero
                                    }"
                                    :disabled="props.isLoading || isConfirming || isAllItemsZero"
                                >
                                    <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                    <Icon v-else icon="solar:plain-bold" class="w-5 h-5" />
                                    {{ isConfirming ? "جاري الإرسال..." : "إرسال الشحنة" }}
                                </button>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    requestData: {
        type: Object,
        default: () => ({
            id: null,
            shipmentNumber: "",
            date: "",
            department: "",
            status: "قيد الاستلام",
            items: [],
        }),
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "send", "reject", "confirm"]);

// البيانات
const receivedItems = ref([]);
const isConfirming = ref(false);
const showRejectionNote = ref(false);
const rejectionNote = ref("");
const rejectionError = ref(false);
const additionalNotes = ref("");
const notesError = ref(false);

// التحقق من حالة الطلب - منع التعديل إذا كان في حالة "قيد الاستلام"
const isProcessing = computed(() => {
    const status = props.requestData.status || "";
    return status === "قيد الاستلام" || status === "approved";
});

// التحقق من أن جميع الأدوية لديها كمية متوفرة أو مرسلة = صفر
const isAllItemsZero = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return true;
    }
    
    // التحقق من أن جميع الأدوية لديها availableQuantity = 0 أو sentQuantity = 0
    return receivedItems.value.every(item => {
        const availableQty = item.availableQuantity || 0;
        const sentQty = item.sentQuantity || 0;
        return availableQty === 0 && sentQty === 0;
    });
});

// التحقق من وجود عنصر واحد على الأقل بكمية مرسلة = 0
const hasZeroQuantityItem = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return false;
    }
    
    // التحقق من وجود عنصر واحد على الأقل بكمية مرسلة = 0
    return receivedItems.value.some(item => {
        const sentQty = item.sentQuantity || 0;
        return sentQty === 0;
    });
});

// التحقق من وجود نقص في الكميات المستلمة (الكمية المستلمة < الكمية المرسلة)
const hasShortage = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return false;
    }
    
    // التحقق من وجود عنصر واحد على الأقل حيث الكمية المستلمة أقل من الكمية المرسلة
    return receivedItems.value.some(item => {
        const receivedQty = item.receivedQuantity || 0;
        const sentQty = item.sentQuantity || 0;
        return receivedQty < sentQty;
    });
});

// إعادة تعيين حالة الرفض عند فتح النموذج
watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            // إعادة تعيين حالة الرفض عند فتح النموذج
            showRejectionNote.value = false;
            rejectionNote.value = "";
            rejectionError.value = false;
            isConfirming.value = false;
            additionalNotes.value = "";
            notesError.value = false;
        }
    }
);

// تهيئة receivedItems
watch(
    () => props.requestData.items,
    (newItems) => {
        if (newItems && newItems.length > 0) {
            console.log('🔍 Processing items for ConfirmationModal:', newItems);
            receivedItems.value = newItems.map((item) => {
                // الحصول على الكمية المتاحة من المخزون - أولوية للقيم الصحيحة من API
                let available = 0;
                if (item.availableQuantity !== undefined && item.availableQuantity !== null) {
                    available = Number(item.availableQuantity);
                } else if (item.stock !== undefined && item.stock !== null) {
                    available = Number(item.stock);
                } else if (item.quantity !== undefined && item.quantity !== null) {
                    available = Number(item.quantity);
                }
                
                // الحصول على الكمية المطلوبة
                let requested = 0;
                if (item.requested_qty !== undefined && item.requested_qty !== null) {
                    requested = Number(item.requested_qty);
                } else if (item.requestedQuantity !== undefined && item.requestedQuantity !== null) {
                    requested = Number(item.requestedQuantity);
                } else if (item.originalQuantity !== undefined && item.originalQuantity !== null) {
                    requested = Number(item.originalQuantity);
                } else if (item.quantity !== undefined && item.quantity !== null) {
                    requested = Number(item.quantity);
                }
                
                // استخدام الكمية المقترحة من الـ API إذا كانت متوفرة
                // الـ API يحسب الكمية المقترحة بناءً على:
                // - إذا كان المخزون كافي لجميع الطلبات: الكمية المطلوبة بالكامل
                // - إذا كان المخزون ناقص: توزيع متساوي حسب نسبة الطلب
                let suggestedQty = 0;
                if (item.suggestedQuantity !== undefined && item.suggestedQuantity !== null) {
                    // استخدام الكمية المقترحة من الـ API مباشرة (الـ API يتأكد من صحة القيمة)
                    suggestedQty = Number(item.suggestedQuantity);
                    // التأكد من أن القيمة صحيحة (للأمان فقط - يجب أن تكون القيمة من API صحيحة)
                    suggestedQty = Math.max(0, Math.min(suggestedQty, available, requested));
                } else {
                    // إذا لم تكن الكمية المقترحة متوفرة من الـ API، استخدام الحد الأدنى كحل احتياطي
                    suggestedQty = Math.max(0, Math.min(requested, available));
                }

                console.log(`📦 Item: ${item.name || item.drug_name || 'غير محدد'}`, {
                    rawItem: item,
                    requested,
                    available,
                    suggestedQty,
                    suggestedQuantityFromAPI: item.suggestedQuantity,
                    availableQuantityFromAPI: item.availableQuantity,
                    stockFromAPI: item.stock,
                    fulfilled_qty: item.fulfilled_qty,
                    fulfilledQty: item.fulfilledQty,
                    fulfilled: item.fulfilled,
                    isProcessing: isProcessing.value
                });

                // عند تأكيد الاستلام (قيد الاستلام)، نستخدم approved_qty (الكمية المرسلة من المستودع)
                // وعند الإرسال، نستخدم suggestedQty كقيمة افتراضية
                let finalSentQty = 0;
                if (isProcessing.value) {
                    // عند تأكيد الاستلام (قيد الاستلام): استخدام approved_qty (الكمية المرسلة من المستودع)
                    // approved_qty = الكمية التي أرسلها المستودع (storekeeper)
                    // fulfilled_qty = الكمية المستلمة من الصيدلية (pharmacist)
                    if (item.approved_qty !== null && item.approved_qty !== undefined) {
                        finalSentQty = Number(item.approved_qty);
                    } else if (item.approvedQty !== null && item.approvedQty !== undefined) {
                        finalSentQty = Number(item.approvedQty);
                    } else if (item.sentQuantity !== null && item.sentQuantity !== undefined) {
                        finalSentQty = Number(item.sentQuantity);
                    } else {
                        finalSentQty = 0;
                    }
                    console.log(`✅ Using approved_qty for sentQuantity: ${finalSentQty} (from approved_qty: ${item.approved_qty}, approvedQty: ${item.approvedQty}, sentQuantity: ${item.sentQuantity})`);
                } else {
                    // عند الإرسال: استخدام suggestedQty كقيمة افتراضية، أو approved_qty إذا كان موجوداً
                    if (item.approved_qty !== null && item.approved_qty !== undefined) {
                        finalSentQty = Number(item.approved_qty);
                    } else if (item.approvedQty !== null && item.approvedQty !== undefined) {
                        finalSentQty = Number(item.approvedQty);
                    } else if (item.sentQuantity !== null && item.sentQuantity !== undefined) {
                        finalSentQty = Number(item.sentQuantity);
                    } else {
                        finalSentQty = suggestedQty > 0 ? suggestedQty : 0;
                    }
                }
                
                return {
                    id: item.id || item.drugId || item.drug_id,
                    name: item.name || item.drugName || item.drug_name || 'دواء غير محدد',
                    originalQuantity: requested,
                    availableQuantity: available,
                    suggestedQuantity: suggestedQty, // الكمية المقترحة من الـ API
                    sentQuantity: finalSentQty, // استخدام fulfilled_qty عند تأكيد الاستلام، أو suggestedQty عند الإرسال
                    receivedQuantity: item.receivedQuantity || item.received_qty || 0, // الكمية المستلمة
                    unit: item.unit || "حبة",
                    dosage: item.dosage || item.strength || ''
                };
            });
            console.log('✅ Final receivedItems:', receivedItems.value);
        } else {
            receivedItems.value = [];
        }
    },
    { immediate: true, deep: true }
);

// إعادة تعيين isConfirming عند انتهاء التحميل
watch(
    () => props.isLoading,
    (newValue) => {
        if (!newValue) {
            isConfirming.value = false;
        }
    }
);

// دالة تنسيق التاريخ
const formatDate = (dateString) => {
    if (!dateString) return "";
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString( {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    } catch {
        return dateString;
    }
};

// التحقق من الكمية المدخلة
const validateQuantity = (index, maxQuantity) => {
    let value = receivedItems.value[index].sentQuantity;

    if (isNaN(value) || value === null) {
        value = 0;
    }
    
    if (value > maxQuantity) {
        value = maxQuantity;
    }
    if (value < 0) {
        value = 0;
    }

    receivedItems.value[index].sentQuantity = Math.floor(value);
};

// التحقق من الكمية المستلمة المدخلة
const validateReceivedQuantity = (index, maxQuantity) => {
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

    receivedItems.value[index].receivedQuantity = Math.floor(value);
};

// بدء عملية الرفض
const initiateRejection = () => {
    showRejectionNote.value = true;
    rejectionNote.value = "";
    rejectionError.value = false;
};

// إلغاء عملية الرفض
const cancelRejection = () => {
    showRejectionNote.value = false;
    rejectionNote.value = "";
    rejectionError.value = false;
};

// تأكيد الرفض
const confirmRejection = () => {
    if (!rejectionNote.value.trim()) {
        rejectionError.value = true;
        return;
    }

    isConfirming.value = true;
    
    const rejectionData = {
        id: props.requestData.id,
        shipmentNumber: props.requestData.shipmentNumber,
        rejectionReason: rejectionNote.value.trim(),
        timestamp: new Date().toISOString()
    };

    emit("reject", rejectionData);
};

// تأكيد الاستلام
const confirmReceipt = async () => {
    // التحقق من صحة الكميات المستلمة
    const hasInvalidQuantity = receivedItems.value.some(
        (item) =>
            item.receivedQuantity === null ||
            item.receivedQuantity === undefined ||
            item.receivedQuantity < 0 ||
            item.receivedQuantity > item.sentQuantity
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المرسلة.");
        return;
    }
    
    // التحقق من ملاحظات الاستلام إذا كان هناك نقص (الكمية المستلمة < الكمية المرسلة)
    if (hasShortage.value && !additionalNotes.value.trim()) {
        notesError.value = true;
        return;
    }
    
    const hasItemsReceived = receivedItems.value.some(item => item.receivedQuantity > 0);
    if (receivedItems.value.length > 0 && !hasItemsReceived) {
        if (!confirm("لم تحدد أي كمية مستلمة. هل تريد تأكيد الاستلام بدون كميات؟")) {
            return;
        }
    }

    isConfirming.value = true;
    
    try {
        const confirmationData = {
            receivedItems: receivedItems.value.map(item => ({
                id: item.id,
                name: item.name,
                originalQuantity: item.originalQuantity,
                receivedQuantity: item.receivedQuantity || 0,
                sentQuantity: item.sentQuantity || 0,
                unit: item.unit
            })),
            notes: additionalNotes.value.trim()
        };

        emit("confirm", confirmationData);
    } catch (error) {
        console.error("Error confirming receipt:", error);
        alert("حدث خطأ أثناء تأكيد الاستلام.");
        isConfirming.value = false;
    }
};

// إرسال الشحنة
const sendShipment = async () => {
    // التحقق من صحة الكميات - لا يمكن إرسال أكثر من الكمية المطلوبة أو المتوفرة
    const hasInvalidQuantity = receivedItems.value.some(
        (item) => {
            const maxAllowed = Math.min(item.availableQuantity || 0, item.originalQuantity || 0);
            return item.sentQuantity === null ||
                item.sentQuantity === undefined ||
                item.sentQuantity < 0 ||
                item.sentQuantity > maxAllowed;
        }
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المطلوبة أو المتوفرة.");
        return;
    }
    
    // التحقق من ملاحظات الإرسال إذا كان هناك عنصر بكمية مرسلة = 0
    if (hasZeroQuantityItem.value && !additionalNotes.value.trim()) {
        notesError.value = true;
      
        return;
    }
    
    const hasItemsToSend = receivedItems.value.some(item => item.sentQuantity > 0);
    if (receivedItems.value.length > 0 && !hasItemsToSend) {
        if (!confirm("لم تحدد أي كمية للإرسال. هل تريد إرسال شحنة فارغة؟ (يمكنك رفض الطلب بدلاً من ذلك).")) {
            return;
        }
    }

    isConfirming.value = true;
    
    try {
        const itemsToSend = receivedItems.value
            .map((item) => ({
                id: item.id,
                name: item.name,
                requestedQuantity: item.originalQuantity,
                sentQuantity: Number(item.sentQuantity) || 0, // التأكد من أن القيمة رقم وليست null/undefined
                unit: item.unit,
            }));
        
        console.log('📦 Sending shipment data:', {
            itemsToSend,
            receivedItems: receivedItems.value,
            allItemsHaveQuantity: itemsToSend.every(item => item.sentQuantity > 0)
        });
        
        const shipmentData = {
            id: props.requestData.id,
            shipmentNumber: props.requestData.shipmentNumber,
            itemsToSend: itemsToSend,
            notes: additionalNotes.value.trim()
        };

        emit("send", shipmentData);
    } catch (error) {
        console.error("Error preparing shipment data:", error);
        alert("حدث خطأ أثناء تحضير بيانات الشحنة.");
        isConfirming.value = false;
    }
};

// إغلاق النموذج
const closeModal = () => {
    if (!props.isLoading && !isConfirming.value) {
        if (showRejectionNote.value) {
            cancelRejection();
        }
        emit("close");
    }
};
</script>

<style scoped>
input[type="number"] {
    -moz-appearance: textfield;
}

.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>