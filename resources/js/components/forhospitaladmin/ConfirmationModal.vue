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
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-extrabold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:package"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
                    معالجة الشحنة رقم {{ requestData.shipmentNumber || "..." }}
                </h3>

                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-white/30"
                    :disabled="props.isLoading || isConfirming"
                    aria-label="إغلاق"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <div
                class="p-3 sm:px-5 sm:py-5 bg-[#F6F4F0] dark:bg-gray-800 space-y-6 max-h-[75vh] overflow-y-auto"
            >
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm border-b pb-4 border-gray-200 dark:border-gray-700">
                    <p class="text-right flex justify-between sm:block">
                        <span class="font-bold text-gray-800 dark:text-gray-200">الجهة الطالبة:</span>
                        <span class="mr-2 text-gray-700 dark:text-gray-300 font-semibold">{{
                            requestData.department || "غير محدد"
                        }}</span>
                    </p>

                    <p class="text-right flex justify-between sm:block">
                        <span class="font-bold text-gray-800 dark:text-gray-200">تاريخ الطلب:</span>
                        <span class="mr-2 text-gray-700 dark:text-gray-300">{{
                            formatDate(requestData.date) || "غير محدد"
                        }}</span>
                    </p>

                    <p class="text-right flex justify-between sm:block">
                        <span class="font-bold text-gray-800 dark:text-gray-200">الحالة:</span>
                        <span class="mr-2 font-extrabold text-orange-500 bg-orange-100 dark:bg-orange-900/50 px-2 py-0.5 rounded-full inline-block">{{
                            requestData.status || "قيد التجهيز"
                        }}</span>
                    </p>
                </div>

                <div class="mt-8">
                    <h3
                        class="text-xl font-bold text-[#15A599] pb-2 mb-4 flex items-center border-b border-dashed border-[#15A599]/50"
                    >
                        <Icon icon="tabler:pill" class="w-6 h-6 ml-2" />
                        الأدوية المطلوبة والمخزون المتاح
                    </h3>

                    <div v-if="invalidQuantityIndices.length > 0 && !showRejectionNote" class="p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm mb-4" role="alert">
                        <Icon icon="tabler:exclamation-circle" class="w-5 h-5 ml-2 inline align-text-bottom" />
                        <strong>انتبه:</strong> يرجى مراجعة الكميات المدخلة للأصناف المحددة باللون الأحمر وتصحيحها لتتوافق مع المخزون المتاح.
                    </div>

                    <div
                        v-if="receivedItems.length > 0"
                        class="space-y-4"
                    >
                        <div
                            v-for="(item, index) in receivedItems"
                            :key="item.id || index"
                            class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg flex flex-col items-start md:flex-row justify-between md:items-center shadow-sm hover:shadow-md transition duration-200"
                            :class="{ 'border-2 border-red-500/50': invalidQuantityIndices.includes(index) }"
                        >
                            <div
                                class="flex-1 w-full flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 md:mb-0 md:pl-20"
                            >
                                <div>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">
                                        {{ item.name }}
                                    </span>
                                    <div v-if="item.dosage" class="text-xs text-gray-500 mt-1">
                                        الجرعة: {{ item.dosage }}
                                    </div>
                                </div>

                                <div
                                    class="text-sm flex flex-col sm:flex-row gap-2 sm:gap-4 items-start sm:items-center"
                                >
                                    <span class="text-gray-700 dark:text-gray-300 font-medium whitespace-nowrap">
                                        مطلوب: {{ item.originalQuantity }} {{ item.unit }}
                                    </span>

                                    <span 
                                        :class="{
                                            'text-green-600 dark:text-green-400': item.availableQuantity >= item.originalQuantity,
                                            'text-red-600 dark:text-red-400': item.availableQuantity < item.originalQuantity
                                        }" 
                                        class="font-medium whitespace-nowrap"
                                    >
                                        متوفر: {{ item.availableQuantity }} {{ item.unit }}
                                    </span>
                                </div>
                            </div>

                           
                        </div>
                    </div>

                    <p v-else class="text-center text-gray-500 dark:text-gray-400 py-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                        لا توجد أدوية في هذا الطلب.
                    </p>
                </div>

                <div
                    v-if="showRejectionNote"
                    class="mt-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg transition-all duration-300"
                >
                    <h4 class="text-lg font-bold text-red-700 dark:text-red-400 mb-3 flex items-center">
                        <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                        سبب رفض الطلب
                    </h4>
                    
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-red-700 dark:text-red-300">
                            يرجى كتابة سبب الرفض (إلزامي) *
                        </label>
                        
                        <textarea
                            v-model="rejectionNote"
                            placeholder="مثال: نقص في المخزون - طلب غير مطابق للسياسات - بيانات ناقصة..."
                            rows="3"
                            class="w-full h-15 px-4 py-3 border-2 border-red-300 dark:border-red-700 focus:border-red-500 dark:focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:focus:ring-red-900 rounded-lg bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 transition-all duration-200 resize-none"
                            :class="{ 'border-red-500': rejectionError }"
                            @input="rejectionError = false"
                        ></textarea>
                        
                        <div v-if="rejectionError" class="text-red-600 dark:text-red-400 text-sm flex items-center">
                            <Icon icon="tabler:alert-circle" class="w-4 h-4 ml-1" />
                            يجب كتابة سبب الرفض قبل الإرسال
                        </div>
                        
                    </div>
                </div>

                <div v-if="!showRejectionNote" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        ملاحظات إضافية (اختياري)
                    </label>
                    <textarea
                        v-model="additionalNotes"
                        placeholder="أضف أي ملاحظات حول الشحنة..."
                        rows="2"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-[#4DA1A9] focus:border-[#4DA1A9] transition duration-200"
                    ></textarea>
                </div>
            </div>

            <div
                class="p-5 sm:px-6 sm:py-4 flex flex-col-reverse sm:flex-row justify-between gap-3 sticky bottom-0 bg-[#F6F4F0] dark:bg-gray-800 rounded-b-xl border-t border-gray-200 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                    :disabled="props.isLoading || isConfirming"
                >
                    إلغاء
                </button>

                <div class="flex gap-3 w-full sm:w-auto">
                    <template v-if="showRejectionNote">
                        <button
                            @click="cancelRejection"
                            class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            إلغاء الرفض
                        </button>
                        
                        <button
                            @click="confirmRejection"
                            class="inline-flex items-center h-11 justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800 font-semibold w-full sm:w-auto"
                            :disabled="isConfirming"
                        >
                            <Icon v-if="isConfirming" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
                            <Icon v-else icon="tabler:check" class="w-5 h-5 ml-2" />
                            تأكيد الرفض
                        </button>
                    </template>

                    <button
                        @click="initiateRejection"
                        class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-full transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#90685e] hover:border hover:border-[#a8a8a8] hover:bg-[#90685ee0] font-semibold w-full sm:w-auto"
                        :disabled="props.isLoading || isConfirming || showRejectionNote"
                        v-if="!showRejectionNote"
                    >
                        <Icon icon="tabler:x" class="w-5 h-5 ml-2" />
                        رفض الطلب
                    </button>

                    <button
                        @click="sendShipment"
                        class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 rounded-full transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] font-semibold w-full sm:w-auto"
                        :disabled="props.isLoading || isConfirming || showRejectionNote"
                        v-if="!showRejectionNote"
                    >
                        <Icon
                            v-if="isConfirming"
                            icon="eos-icons:loading"
                            class="w-5 h-5 ml-2 animate-spin"
                        />
                        <Icon v-else icon="tabler:check" class="w-5 h-5 ml-2" />
                        {{ isConfirming ? "جاري القبول..." : " قبول الطلب" }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
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
const invalidQuantityIndices = ref([]); // متغير لتتبع الأخطاء في الكميات

// تهيئة receivedItems
watch(
    () => props.requestData.items,
    (newItems) => {
        if (newItems && newItems.length > 0) {
            receivedItems.value = newItems.map((item) => {
                // الحصول على الكمية المتاحة من المخزون (يجب أن تأتي من API)
                const available = Number(
                    item.availableQuantity ||
                    item.stock ||
                    item.quantity ||
                    0
                );
                const requested = Number(
                    item.quantity || item.requestedQuantity || 0
                );

                return {
                    id: item.id || item.drugId,
                    name: item.name || item.drugName,
                    originalQuantity: requested,
                    availableQuantity: available,
                    // عند التهيئة، يتم وضع القيمة التي طلبها المستخدم أو المتوفر، أيهما أقل
                    sentQuantity: Math.min(requested, available),
                    unit: item.unit || "حبة",
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
        return date.toLocaleDateString('ar-SA', {
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
    
    // التأكد من أن القيمة المدخلة لا تتجاوز المتوفر ولا تقل عن الصفر
    value = Math.max(0, Math.min(maxQuantity, value));

    receivedItems.value[index].sentQuantity = Math.floor(value);
    
    // إزالة مؤشر الخطأ إذا تم التصحيح
    if (invalidQuantityIndices.value.includes(index) && value <= maxQuantity && value >= 0) {
        invalidQuantityIndices.value = invalidQuantityIndices.value.filter(i => i !== index);
    }
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

    // تم الإبقاء على confirm هنا لأنه إجراء حاسم (رفض الطلب بالكامل)
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

// إرسال الشحنة (تم إزالة كل تنبيهات المتصفح)
const sendShipment = async () => {
    // 1. تصفير قائمة الأخطاء قبل البدء بالتحقق
    invalidQuantityIndices.value = [];
    
    // 2. التحقق من صحة الكميات وتعبئة قائمة الأخطاء
    receivedItems.value.forEach((item, index) => {
        if (
            item.sentQuantity === null ||
            item.sentQuantity === undefined ||
            item.sentQuantity < 0 ||
            item.sentQuantity > item.availableQuantity
        ) {
            invalidQuantityIndices.value.push(index);
        }
    });

    // 3. إذا كان هناك كميات غير صحيحة، إيقاف الإرسال
    if (invalidQuantityIndices.value.length > 0) {
        return; 
    }
    
    // 4. إزالة التحقق من الشحنة الفارغة الذي يظهر Confirm - (بناءً على طلبك)
    
    isConfirming.value = true;
    
    try {
        const shipmentData = {
            id: props.requestData.id,
            shipmentNumber: props.requestData.shipmentNumber,
            itemsToSend: receivedItems.value
                .filter(item => item.sentQuantity > 0) // نرسل فقط الأصناف التي تم تحديد كمية لها (> 0)
                .map((item) => ({
                    id: item.id,
                    name: item.name,
                    requestedQuantity: item.originalQuantity,
                    sentQuantity: item.sentQuantity,
                    unit: item.unit,
                })),
            notes: additionalNotes.value.trim()
        };

        emit("send", shipmentData);
    } catch (error) {
        console.error("Error preparing shipment data:", error);
        // تم الإبقاء على alert لخطأ نظام غير متوقع (Error preparing shipment data)
        alert("حدث خطأ أثناء تحضير بيانات الشحنة."); 
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

.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[75vh\] {
    max-height: 75vh;
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>