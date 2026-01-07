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
                
                <!-- Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                   

                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium mb-1">تاريخ الطلب</p>
                            <p class="text-[#2E5077] font-bold text-lg">{{ formatDate(requestData.date) || "غير محدد" }}</p>
                        </div>
                        <div class="w-10 h-10 bg-purple-50 rounded-full flex items-center justify-center">
                            <Icon icon="solar:calendar-bold-duotone" class="w-6 h-6 text-purple-600" />
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium mb-1">الحالة</p>
                            <span class="inline-block px-3 py-1 rounded-lg text-sm font-bold bg-orange-100 text-orange-600">
                                {{ requestData.status || "قيد التجهيز" }}
                            </span>
                        </div>
                        <div class="w-10 h-10 bg-orange-50 rounded-full flex items-center justify-center">
                            <Icon icon="solar:clock-circle-bold-duotone" class="w-6 h-6 text-orange-600" />
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:pill-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الأدوية المطلوبة والمخزون المتاح
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
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <h4 class="font-bold text-[#2E5077] text-lg">{{ item.name }}</h4>
                                            <span v-if="item.strength || item.dosage" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-md font-medium">
                                                القوة: {{ item.strength || item.dosage }}
                                            </span>
                                            <span v-if="item.unit" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-md font-medium">
                                                الوحدة: {{ item.unit }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center gap-4 text-sm mt-2">
                                            <div class="flex items-center gap-1 text-gray-600 bg-gray-50 px-2 py-1 rounded-lg border border-gray-100">
                                                <span class="font-medium">مطلوب:</span>
                                                <span class="font-bold text-[#2E5077]">{{ item.originalQuantity }} {{ item.unit }}</span>
                                            </div>
                                            
                                            <div 
                                                class="flex items-center gap-1 px-2 py-1 rounded-lg border"
                                                :class="{
                                                    'bg-green-50 border-green-100 text-green-700': item.availableQuantity >= item.originalQuantity,
                                                    'bg-red-50 border-red-100 text-red-700': item.availableQuantity < item.originalQuantity
                                                }"
                                            >
                                                <span class="font-medium">متوفر:</span>
                                                <span class="font-bold">{{ item.availableQuantity }} {{ item.unit }}</span>
                                                <Icon v-if="item.availableQuantity < item.originalQuantity" icon="solar:danger-circle-bold" class="w-4 h-4" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action -->
                                    <div class="flex items-center gap-3 w-full md:w-auto bg-gray-50 p-2 rounded-xl border border-gray-200">
                                        <label :for="`sent-qty-${index}`" class="text-sm font-bold text-gray-500 px-2">
                                            الكمية المرسلة:
                                        </label>
                                        <input
                                            :id="`sent-qty-${index}`"
                                            type="number"
                                            v-model.number="item.sentQuantity"
                                            :max="item.availableQuantity"
                                            :min="0"
                                            class="w-24 h-10 text-center bg-white border rounded-lg focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077] text-lg"
                                            :class="{
                                                'border-red-300 focus:border-red-500': item.sentQuantity > item.availableQuantity,
                                                'border-green-300 focus:border-green-500': item.sentQuantity <= item.availableQuantity && item.sentQuantity > 0,
                                                'border-gray-200 focus:border-[#4DA1A9]': item.sentQuantity === 0
                                            }"
                                            @input="validateQuantity(index, item.availableQuantity)"
                                            :disabled="props.isLoading || isConfirming"
                                        />
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
                            :class="rejectionError ? 'border-red-500 focus:border-red-500' : 'border-red-200 focus:border-red-400'"
                            @input="rejectionError = false"
                        ></textarea>
                        
                        <div v-if="rejectionError" class="text-red-600 text-sm flex items-center gap-1 font-medium">
                            <Icon icon="solar:danger-circle-bold" class="w-4 h-4" />
                            يجب كتابة سبب الرفض قبل الإرسال
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div v-if="!showRejectionNote" class="space-y-2">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        ملاحظات الإرسال 
                        <span v-if="hasZeroQuantityItem" class="text-sm font-normal text-red-600">(إلزامي)</span>
                        <span v-else class="text-sm font-normal text-gray-400">(اختياري)</span>
                    </h3>
                    <textarea
                        v-model="additionalNotes"
                        :placeholder="hasZeroQuantityItem ? 'يجب إضافة ملاحظات عند وجود عنصر بكمية مرسلة = 0...' : 'أضف أي ملاحظات حول الشحنة...'"
                        rows="2"
                        class="w-full p-4 bg-white border rounded-xl text-gray-700 focus:ring-2 transition-all resize-none"
                        :class="{
                            'border-red-500 focus:border-red-500 focus:ring-red-500/20': hasZeroQuantityItem && notesError,
                            'border-orange-300 focus:border-orange-500 focus:ring-orange-500/20': hasZeroQuantityItem && !notesError,
                            'border-gray-200 focus:border-[#4DA1A9] focus:ring-[#4DA1A9]/20': !hasZeroQuantityItem
                        }"
                        @input="notesError = false"
                    ></textarea>
                    <div v-if="hasZeroQuantityItem && notesError" class="text-red-600 text-sm flex items-center gap-1 font-medium">
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
                        <button
                            @click="initiateRejection"
                            class="px-6 py-2.5 rounded-xl text-red-500 bg-red-50 border border-red-100 font-medium hover:bg-red-100 transition-colors duration-200 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="props.isLoading || isConfirming"
                        >
                            <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                            رفض الطلب
                        </button>

                        <button
                            @click="sendShipment"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-[#4DA1A9]"
                            :disabled="props.isLoading || isConfirming || !canSendShipment"
                        >
                            <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:plain-bold" class="w-5 h-5" />
                            {{ isConfirming ? "جاري الإرسال..." : "إرسال الشحنة" }}
                        </button>
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
            status: "قيد التجهيز",
            items: [],
        }),
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "send", "reject"]);

// البيانات
const receivedItems = ref([]);
const isConfirming = ref(false);
const showRejectionNote = ref(false);
const rejectionNote = ref("");
const rejectionError = ref(false);
const additionalNotes = ref("");
const notesError = ref(false);

// تهيئة receivedItems
watch(
    () => props.requestData.items,
    (newItems) => {
        if (newItems && newItems.length > 0) {
            receivedItems.value = newItems.map((item) => {
                // الحصول على الكمية المطلوبة
                const requested = Number(
                    item.requestedQuantity || 
                    item.requested_qty || 
                    item.quantity || 
                    item.originalQuantity ||
                    0
                );
                
                // الحصول على الكمية المتوفرة الفعلية من مخزون المورد
                // يجب أن تأتي من API (availableQuantity) - الكمية الفعلية في مخزون المورد
                const available = Number(
                    item.availableQuantity !== undefined && item.availableQuantity !== null 
                        ? item.availableQuantity 
                        : 0
                );

                return {
                    id: item.id || item.drugId,
                    name: item.name || item.drugName,
                    originalQuantity: requested,
                    availableQuantity: available,
                    sentQuantity: Math.min(requested, available), // افتراضي: إرسال الكمية المعتمدة
                    unit: item.unit || "وحدة",
                    dosage: item.dosage || item.strength
                };
            });
        } else {
            receivedItems.value = [];
        }
    },
    { immediate: true, deep: true }
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

// التحقق من إمكانية إرسال الشحنة
// يتم تعطيل الزر إذا كانت كل الأدوية لديها الكمية المتوفرة = 0 أو الكمية المرسلة = 0
const canSendShipment = computed(() => {
    if (!receivedItems.value || receivedItems.value.length === 0) {
        return false;
    }
    
    // التحقق من وجود دواء واحد على الأقل لديه كمية متوفرة > 0 وكمية مرسلة > 0
    const hasValidItem = receivedItems.value.some(
        (item) => 
            (item.availableQuantity > 0) && (item.sentQuantity > 0)
    );
    
    return hasValidItem;
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
    // لا نعيد تعيين isConfirming هنا، سيتم إعادة تعيينه في handleConfirmation
};

// إرسال الشحنة
const sendShipment = async () => {
    // التحقق من صحة الكميات
    const hasInvalidQuantity = receivedItems.value.some(
        (item) =>
            item.sentQuantity === null ||
            item.sentQuantity === undefined ||
            item.sentQuantity < 0 ||
            item.sentQuantity > item.availableQuantity
    );
    
    if (hasInvalidQuantity) {
        // التحقق من صحة الكميات - إرجاع بدون alert
        return;
    }
    
    // التحقق من ملاحظات الإرسال إذا كان هناك عنصر بكمية مرسلة = 0
    if (hasZeroQuantityItem.value && !additionalNotes.value.trim()) {
        notesError.value = true;
        return;
    }
    
    const hasItemsToSend = receivedItems.value.some(item => item.sentQuantity > 0);
    if (receivedItems.value.length > 0 && !hasItemsToSend) {
        // لا توجد كمية للإرسال - يمكن المتابعة بدون تأكيد
    }

    isConfirming.value = true;
    
    try {
        const shipmentData = {
            id: props.requestData.id,
            shipmentNumber: props.requestData.shipmentNumber,
            items: receivedItems.value
                .filter(item => item.sentQuantity > 0)
                .map((item) => ({
                    id: item.id,
                    fulfilled_qty: item.sentQuantity,
                    sentQuantity: item.sentQuantity, // للتوافق
                })),
            itemsToSend: receivedItems.value
                .filter(item => item.sentQuantity > 0)
                .map((item) => ({
                    id: item.id,
                    name: item.name,
                    requestedQuantity: item.originalQuantity,
                    sentQuantity: item.sentQuantity,
                    fulfilled_qty: item.sentQuantity,
                    unit: item.unit,
                })),
            notes: additionalNotes.value.trim()
        };

        emit("send", shipmentData);
        // لا نعيد تعيين isConfirming هنا، سيتم إعادة تعيينه في handleConfirmation
    } catch (error) {
        console.error("Error preparing shipment data:", error);
        isConfirming.value = false; // إعادة تعيين في حالة الخطأ
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
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

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