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
                    تأكيد قبول الشحنة رقم {{ requestData.shipmentNumber || "..." }}
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- معلومات الشحنة -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium mb-1">الجهة الطالبة</p>
                            <p class="text-[#2E5077] font-bold text-lg">{{ requestData.department || requestData.requestingDepartment || "غير محدد" }}</p>
                        </div>
                        <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                            <Icon icon="solar:hospital-bold-duotone" class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>

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

                <!-- العناصر - عرض فقط -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:box-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        العناصر المشحونة
                    </h3>

                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div v-if="requestData.items && requestData.items.length > 0" class="divide-y divide-gray-50">
                            <div 
                                v-for="(item, index) in requestData.items" 
                                :key="item.id || index"
                                class="p-5 hover:bg-gray-50/50 transition-colors"
                            >
                                <div class="flex justify-between items-start gap-4">
                                    <!-- معلومات الصنف -->
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="font-bold text-[#2E5077] text-lg">{{ item.name || item.drugName }}</h4>
                                            <span v-if="item.dosage || item.strength" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-md font-medium">
                                                {{ item.dosage || item.strength }}
                                            </span>
                                        </div>
                                        
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span v-if="item.category" class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                {{ item.category }}
                                            </span>
                                            <span v-if="item.type" class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100">
                                                {{ item.type }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- الكميات -->
                                    <div class="flex flex-col items-end gap-2">
                                        <div class="flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                            <span class="text-gray-500 text-sm font-medium">المطلوب:</span>
                                            <span class="text-[#4DA1A9] font-bold text-lg">
                                                {{ item.quantity || item.requestedQuantity }} 
                                                <span class="text-sm font-normal text-gray-500">{{ item.unit || 'وحدة' }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-gray-500">
                            <Icon icon="solar:box-minimalistic-broken" class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            لا توجد عناصر في هذا الطلب
                        </div>
                    </div>
                </div>

              

                <!-- رسالة الخطأ -->
                <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-2xl p-4">
                    <div class="flex items-center gap-2 text-red-600">
                        <Icon icon="solar:danger-circle-bold" class="w-5 h-5" />
                        <span class="font-medium">{{ errorMessage }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex flex-col-reverse sm:flex-row justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-6 py-2.5 rounded-xl text-gray-600 bg-gray-100 font-medium hover:bg-gray-200 transition-colors duration-200 w-full sm:w-auto"
                    :disabled="isConfirming"
                    :class="{ 'opacity-50 cursor-not-allowed': isConfirming }"
                >
                    إلغاء
                </button>
                
                <button
                    @click="confirmReceipt"
                    class="px-6 py-2.5 rounded-xl bg-green-500 text-white font-medium hover:bg-green-600 transition-colors duration-200 shadow-lg shadow-green-500/20 flex items-center justify-center gap-2 w-full sm:w-auto"
                    :disabled="isConfirming"
                    :class="{ 'opacity-50 cursor-not-allowed': isConfirming }"
                >
                    <Icon v-if="isConfirming" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                    <Icon v-else icon="solar:check-circle-bold" class="w-5 h-5" />
                    {{ isConfirming ? "جاري تأكيد القبول..." : "تأكيد القبول" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import axios from 'axios';

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
const isConfirming = ref(false);
const additionalNotes = ref("");
const errorMessage = ref("");
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:3000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة التوكن تلقائياً
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
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
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch {
        return dateString;
    }
};

// تأكيد استلام الشحنة عبر API
const confirmReceipt = async () => {
    if (!props.requestData.id) {
        errorMessage.value = "رقم الشحنة غير صالح";
        return;
    }

    isConfirming.value = true;
    errorMessage.value = "";
    
    try {
        // تحضير البيانات لتأكيد الاستلام
        const confirmationData = {
            items: props.requestData.items?.map((item) => ({
                id: item.id,
                name: item.name || item.drugName,
                receivedQuantity: item.quantity || item.requestedQuantity,
                unit: item.unit,
                category: item.category,
                type: item.type
            })) || [],
            notes: additionalNotes.value.trim(),
            confirmedAt: new Date().toISOString()
        };

        // استدعاء API لتأكيد الاستلام
        const response = await api.put(`/shipments/${props.requestData.id}/confirm`, confirmationData);
        
        if (response.data.success) {
            // إرسال البيانات المحدثة إلى المكون الأب
            emit("confirm", {
                ...confirmationData,
                shipmentId: props.requestData.id,
                shipmentNumber: props.requestData.shipmentNumber
            });
        } else {
            throw new Error(response.data.message || "فشل في  القبول");
        }
        
    } catch (error) {
        console.error("Error confirming receipt:", error);
        
        // معالجة الأخطاء المختلفة
        if (error.response) {
            if (error.response.status === 401) {
                errorMessage.value = "جلسة العمل منتهية، يرجى تسجيل الدخول مرة أخرى";
            } else if (error.response.status === 404) {
                errorMessage.value = "الشحنة غير موجودة أو تم حذفها";
            } else if (error.response.status === 400) {
                errorMessage.value = error.response.data.message || "بيانات غير صالحة";
            } else if (error.response.status === 409) {
                errorMessage.value = "تم تأكيد القبول مسبقاً";
            } else {
                errorMessage.value = `خطأ في الخادم: ${error.response.status}`;
            }
        } else if (error.request) {
            errorMessage.value = "تعذر الاتصال بالخادم، يرجى التحقق من اتصالك بالإنترنت";
        } else {
            errorMessage.value = error.message || "حدث خطأ غير متوقع";
        }
        
        // إعادة تعيين حالة التأكيد
        isConfirming.value = false;
    }
};

// إغلاق النموذج
const closeModal = () => {
    if (!isConfirming.value) {
        additionalNotes.value = "";
        errorMessage.value = "";
        emit("close");
    }
};

// مراقبة فتح/إغلاق المودال
watch(() => props.isOpen, (newVal) => {
    if (!newVal) {
        additionalNotes.value = "";
        errorMessage.value = "";
        isConfirming.value = false;
    }
});
</script>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* تحسينات للـ textarea */
textarea::placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
}

textarea:focus {
    outline: none;
}
</style>