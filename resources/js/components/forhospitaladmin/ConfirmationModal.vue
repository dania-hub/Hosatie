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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium mb-1">تاريخ الطلب</p>
                            <p class="text-[#2E5077] font-bold text-lg">{{ formatDate(requestData.createdAt || requestData.date) || "غير محدد" }}</p>
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
                        الأدوية المطلوبة
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
                                                <span class="font-medium">الكمية المطلوبة:</span>
                                                <span class="font-bold text-[#2E5077]">{{ item.requestedQuantity }} {{ item.unit }}</span>
                                            </div>
                                            
                                            <div 
                                                class="flex items-center gap-1 px-2 py-1 rounded-lg border"
                                                :class="{
                                                    'bg-green-50 border-green-100 text-green-700': item.availableQuantity >= item.requestedQuantity,
                                                    'bg-red-50 border-red-100 text-red-700': item.availableQuantity < item.requestedQuantity
                                                }"
                                            >
                                                <span class="font-medium">الكمية المتوفرة:</span>
                                                <span class="font-bold">{{ item.availableQuantity || 0 }} {{ item.unit }}</span>
                                                <Icon v-if="item.availableQuantity < item.requestedQuantity" icon="solar:danger-circle-bold" class="w-4 h-4" />
                                            </div>
                                        </div>
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
                            :disabled="props.isLoading || isConfirming || showRejectionNote"
                        >
                            <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                            رفض الطلب
                        </button>

                        <button
                            @click="sendShipment"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#3a8c94] transition-colors duration-200 shadow-lg shadow-[#4DA1A9]/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                            :disabled="props.isLoading || isConfirming || showRejectionNote"
                        >
                            <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            <Icon v-else icon="solar:check-circle-bold" class="w-5 h-5" />
                            {{ isConfirming ? "جاري القبول..." : "القبول" }}
                        </button>
                    </template>
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

const emit = defineEmits(["close", "confirm"]);

// البيانات
const receivedItems = ref([]);
const isConfirming = ref(false);
const showRejectionNote = ref(false);
const rejectionNote = ref("");
const rejectionError = ref(false);

// تهيئة receivedItems
watch(
    () => props.requestData.items,
    (newItems) => {
        console.log('ConfirmationModal - Received items:', newItems);
        if (newItems && newItems.length > 0) {
            receivedItems.value = newItems.map((item) => {
                const mappedItem = {
                    id: item.id || item.drugId,
                    name: item.name || item.drugName,
                    requestedQuantity: Number(item.requestedQuantity || item.requested || item.requested_qty || item.quantity || 0),
                    availableQuantity: Number(item.availableQuantity || item.available_quantity || item.stock || 0),
                    unit: item.unit || "وحدة",
                    dosage: item.dosage || item.strength
                };
                console.log('Mapped item:', mappedItem);
                return mappedItem;
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
        rejectionReason: rejectionNote.value.trim(),
        items: []
    };

    emit("confirm", rejectionData);
};

// إرسال الشحنة (القبول المبدئي)
const sendShipment = async () => {
    isConfirming.value = true;
    
    try {
        // عند القبول المبدئي، نرسل فقط id لكل عنصر
        // الـ API سيضع approved_qty = requested_qty تلقائياً
        const confirmationData = {
            items: receivedItems.value.map((item) => ({
                id: item.id
                // لا نرسل sent لأن القبول مبدئي
                // المورد لاحقاً سيحدد الكمية الفعلية المرسلة
            }))
        };

        console.log('Sending confirmation data (preliminary approval):', confirmationData);
        emit("confirm", confirmationData);
    } catch (error) {
        console.error("Error preparing shipment data:", error);
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