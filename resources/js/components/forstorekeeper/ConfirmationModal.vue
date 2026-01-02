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
                            <p class="text-sm text-gray-500 font-medium mb-1">الجهة الطالبة</p>
                            <p class="text-[#2E5077] font-bold text-lg">{{ requestData.department || "غير محدد" }}</p>
                        </div>
                        <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                            <Icon icon="solar:hospital-bold-duotone" class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>

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
                                {{ requestData.status || "قيد الاستلام" }}
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
                                        
                                        <div class="flex items-center gap-4 text-sm mt-2">
                                            <div class="flex items-center gap-1 text-gray-600 bg-gray-50 px-2 py-1 rounded-lg border border-gray-100">
                                                <span class="font-medium">مطلوب:</span>
                                                <span class="font-bold text-[#2E5077]">{{ item.originalQuantity }} {{ item.unit }}</span>
                                            </div>
                                            
                                            <!-- عرض "مرسل" عند تأكيد الاستلام -->
                                            <div v-if="isProcessing" class="flex items-center gap-1 bg-green-50 px-2 py-1 rounded-lg border border-green-100 text-green-700">
                                                <span class="font-medium">مرسل:</span>
                                                <span class="font-bold">{{ item.sentQuantity || 0 }} {{ item.unit }}</span>
                                            </div>
                                            
                                            <div 
                                                v-if="!isProcessing"
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

                                    <!-- Actions -->
                                    <div class="flex flex-col md:flex-row items-start md:items-center gap-3 w-full md:w-auto">
                                        <!-- عند تأكيد الاستلام: عرض حقل "مستلم" فقط -->
                                        <template v-if="isProcessing">
                                            <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-xl border border-gray-200">
                                                <label :for="`received-qty-${index}`" class="text-sm font-bold text-gray-500 px-2">
                                                    مستلم:
                                                </label>
                                                <input
                                                    :id="`received-qty-${index}`"
                                                    type="number"
                                                    v-model.number="item.receivedQuantity"
                                                    :max="item.sentQuantity || item.originalQuantity"
                                                    :min="0"
                                                    class="w-24 h-10 text-center bg-white border rounded-lg focus:ring-2 focus:ring-[#4DA1A9]/20 outline-none transition-all font-bold text-[#2E5077] text-lg"
                                                    :class="{
                                                        'border-green-300 focus:border-green-500': item.receivedQuantity === (item.sentQuantity || item.originalQuantity),
                                                        'border-orange-300 focus:border-orange-500': item.receivedQuantity > 0 && item.receivedQuantity < (item.sentQuantity || item.originalQuantity),
                                                        'border-gray-200 focus:border-[#4DA1A9]': item.receivedQuantity === 0
                                                    }"
                                                    @input="validateReceivedQuantity(index, item.sentQuantity || item.originalQuantity)"
                                                    :disabled="props.isLoading || isConfirming"
                                                />
                                            </div>
                                        </template>
                                        
                                        <!-- عند الإرسال: عرض الكمية المقترحة والمرسلة -->
                                        <template v-else>
                                            <!-- الكمية المقترحة -->
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
                            'border-red-200 focus:border-red-400': !rejectionError,
                            'bg-gray-100 cursor-not-allowed': isProcessing
                        }"
                        @input="rejectionError = false"
                        :disabled="isProcessing"
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
                        ملاحظات إضافية <span class="text-sm font-normal text-gray-400">(اختياري)</span>
                    </h3>
                    <textarea
                        v-model="additionalNotes"
                        placeholder="أضف أي ملاحظات حول الشحنة..."
                        rows="2"
                        class="w-full p-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                        :class="{ 'bg-gray-100 ': isProcessing }"
                       
                    ></textarea>
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
                                class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :disabled="props.isLoading || isConfirming"
                            >
                                <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                <Icon v-else icon="solar:check-circle-bold" class="w-5 h-5" />
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

                            <button
                                @click="sendShipment"
                                class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                                :class="{
                                    'opacity-50 cursor-not-allowed': isAllItemsZero,
                                    'hover:bg-[#3a8c94]': !isAllItemsZero
                                }"
                                :disabled="props.isLoading || isConfirming || isAllItemsZero"
                            >
                                <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                                <Icon v-else icon="solar:plain-bold" class="w-5 h-5" />
                                {{ isConfirming ? "جاري الإرسال..." : "إرسال الشحنة" }}
                            </button>
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

    if (confirm("هل أنت متأكد من رفض هذا الطلب؟ سيتم إلغاء الطلب بالكامل.")) {
        isConfirming.value = true;
        
        const rejectionData = {
            id: props.requestData.id,
            shipmentNumber: props.requestData.shipmentNumber,
            rejectionReason: rejectionNote.value.trim(),
            timestamp: new Date().toISOString()
        };

        emit("reject", rejectionData);
    }
};

// تأكيد الاستلام
const confirmReceipt = async () => {
    // التحقق من صحة الكميات المستلمة
    const hasInvalidQuantity = receivedItems.value.some(
        (item) =>
            item.receivedQuantity === null ||
            item.receivedQuantity === undefined ||
            item.receivedQuantity < 0 ||
            item.receivedQuantity > (item.sentQuantity || item.originalQuantity)
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المرسلة.");
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
    // التحقق من صحة الكميات
    const hasInvalidQuantity = receivedItems.value.some(
        (item) =>
            item.sentQuantity === null ||
            item.sentQuantity === undefined ||
            item.sentQuantity < 0 ||
            item.sentQuantity > item.availableQuantity
    );
    
    if (hasInvalidQuantity) {
        alert("يرجى التأكد من إدخال كميات صحيحة لجميع الأصناف، وأنها لا تتجاوز الكمية المتوفرة.");
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
            .filter(item => item.sentQuantity > 0)
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