<script setup>
import { ref, watch } from 'vue';
import { Icon } from "@iconify/vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    requestData: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['close', 'submit']);

const selectedAction = ref(null);
const responseText = ref('');
const rejectionReason = ref('');
const additionalNotes = ref('');

// إعادة تعيين الحقول عند فتح المودال
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        selectedAction.value = null;
        responseText.value = '';
        rejectionReason.value = '';
        additionalNotes.value = '';
    }
});

// دوال مساعدة
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('ar-SA');
    } catch {
        return dateString;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'تم الرد':
            return 'bg-green-100 text-green-700 border border-green-200';
        case 'قيد المراجعة':
            return 'bg-yellow-100 text-yellow-700 border border-yellow-200';
        case 'مرفوض':
            return 'bg-red-100 text-red-700 border border-red-200';
        default:
            return 'bg-gray-100 text-gray-700 border border-gray-200';
    }
};

const selectAction = (action) => {
    selectedAction.value = action;
};

const submitResponse = () => {
    if (!selectedAction.value) return;
    
    if (selectedAction.value === 'reject' && !rejectionReason.value.trim()) {
        return;
    }
    
    const responseData = {
        status: selectedAction.value === 'accept' ? 'تم الرد' : 'مرفوض',
        response: responseText.value.trim(),
        reason: rejectionReason.value.trim(),
        notes: additionalNotes.value.trim(),
        date: new Date().toISOString(),
        requestDetails: {
            fileNumber: props.requestData?.fileNumber,
            patientName: props.requestData?.patientName,
            requestType: props.requestData?.requestType,
            content: props.requestData?.content
        }
    };
    
    emit('submit', responseData);
};

const closeModal = () => {
    emit('close');
};
</script>

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
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all scale-100"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:chat-round-line-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    الرد على الطلب
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                <!-- بيانات المريض -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:user-circle-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات المريض
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">رقم الملف</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ requestData?.fileNumber || 'غير محدد' }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">اسم المريض</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077]">
                                {{ requestData?.patientName || 'غير محدد' }}
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">نوع الطلب</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077]">
                                {{ requestData?.requestType || 'غير محدد' }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-gray-500 font-medium">التاريخ</Label>
                            <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 font-bold text-[#2E5077] font-mono">
                                {{ formatDate(requestData?.createdDate) || 'غير محدد' }}
                            </div>
                        </div>

                        <div v-if="requestData?.content" class="sm:col-span-2 space-y-2">
                            <Label class="text-gray-500 font-medium">المحتوى</Label>
                            <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 text-gray-700 leading-relaxed">
                                {{ requestData.content }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- حالة الطلب -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-6 flex items-center gap-2">
                        <Icon icon="solar:clipboard-check-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        حالة الطلب
                    </h3>

                    <div class="flex items-center gap-4">
                        <Label class="text-gray-500 font-medium">الحالة الحالية:</Label>
                        <span :class="[getStatusClass(requestData?.requestStatus), 'px-4 py-2 rounded-xl text-sm font-bold']">
                            {{ requestData?.requestStatus || 'غير محدد' }}
                        </span>
                    </div>
                </div>

                <!-- خيارات الرد -->
                <div class="space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:chat-line-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        خيارات الرد
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            @click="selectAction('accept')"
                            :class="[
                                'p-6 rounded-2xl border-2 flex flex-col items-center justify-center transition-all duration-200 gap-3',
                                selectedAction === 'accept'
                                    ? 'bg-green-50 border-green-500 text-green-700 shadow-lg shadow-green-100'
                                    : 'bg-white border-gray-100 text-gray-500 hover:border-green-200 hover:bg-green-50/50'
                            ]"
                        >
                            <div :class="['p-3 rounded-full', selectedAction === 'accept' ? 'bg-green-100' : 'bg-gray-100']">
                                <Icon icon="solar:check-circle-bold" class="w-8 h-8" />
                            </div>
                            <span class="font-bold text-lg">قبول الطلب</span>
                            <span class="text-sm opacity-80">وإرسال الرد</span>
                        </button>
                        
                        <button
                            @click="selectAction('reject')"
                            :class="[
                                'p-6 rounded-2xl border-2 flex flex-col items-center justify-center transition-all duration-200 gap-3',
                                selectedAction === 'reject'
                                    ? 'bg-red-50 border-red-500 text-red-700 shadow-lg shadow-red-100'
                                    : 'bg-white border-gray-100 text-gray-500 hover:border-red-200 hover:bg-red-50/50'
                            ]"
                        >
                            <div :class="['p-3 rounded-full', selectedAction === 'reject' ? 'bg-red-100' : 'bg-gray-100']">
                                <Icon icon="solar:close-circle-bold" class="w-8 h-8" />
                            </div>
                            <span class="font-bold text-lg">رفض الطلب</span>
                            <span class="text-sm opacity-80">مع ذكر السبب</span>
                        </button>
                    </div>
                </div>

                <!-- حقل الرد (قبول) -->
                <div v-if="selectedAction === 'accept'" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 animate-in fade-in slide-in-from-top-4">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        الرد
                    </h3>
                    
                    <div class="mb-4 bg-green-50 p-4 rounded-xl border border-green-100">
                        <p class="font-bold text-green-800 mb-2 flex items-center gap-2">
                            <Icon icon="solar:info-circle-bold" class="w-5 h-5" />
                            نصائح للرد:
                        </p>
                        <ul class="space-y-1 text-sm text-green-700 list-disc list-inside">
                            <li>تفاصيل القبول والخطوات التالية</li>
                            <li>المواعيد المقترحة</li>
                            <li>التعليمات أو الشروط</li>
                        </ul>
                    </div>

                    <textarea
                        v-model="responseText"
                        rows="5"
                        class="w-full p-4 rounded-xl bg-gray-50 border-2 border-gray-100 focus:border-[#4DA1A9] focus:ring-4 focus:ring-[#4DA1A9]/10 transition-all outline-none resize-none"
                        placeholder="أدخل ردك هنا..."
                    ></textarea>
                </div>

                <!-- سبب الرفض (رفض) -->
                <div v-if="selectedAction === 'reject'" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 animate-in fade-in slide-in-from-top-4">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:danger-circle-bold-duotone" class="w-6 h-6 text-red-500" />
                        سبب الرفض
                    </h3>
                    
                    <div class="mb-4 bg-red-50 p-4 rounded-xl border border-red-100">
                        <p class="font-bold text-red-800 mb-2 flex items-center gap-2">
                            <Icon icon="solar:shield-warning-bold" class="w-5 h-5" />
                            ملاحظات هامة:
                        </p>
                        <p class="text-sm text-red-700">
                            اكتب السبب المنطقي لرفض الطلب. يجب أن يكون السبب واضحاً ويحترم المريض. سيتم إعلام المريض برفض طلبه.
                        </p>
                    </div>

                    <textarea
                        v-model="rejectionReason"
                        rows="5"
                        class="w-full p-4 rounded-xl bg-gray-50 border-2 border-gray-100 focus:border-red-500 focus:ring-4 focus:ring-red-500/10 transition-all outline-none resize-none"
                        placeholder="أدخل سبب الرفض..."
                        required
                    ></textarea>
                </div>

                <!-- ملاحظات إضافية -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4 flex items-center gap-2">
                        <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        ملاحظات إضافية
                    </h3>
                    
                    <textarea
                        v-model="additionalNotes"
                        rows="3"
                        class="w-full p-4 rounded-xl bg-gray-50 border-2 border-gray-100 focus:border-[#4DA1A9] focus:ring-4 focus:ring-[#4DA1A9]/10 transition-all outline-none resize-none"
                        placeholder="أدخل أي ملاحظات إضافية..."
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    @click="closeModal"
                    class="px-6 py-3 rounded-xl text-[#2E5077] font-bold hover:bg-gray-100 transition-all duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="submitResponse"
                    :disabled="!selectedAction || (selectedAction === 'reject' && !rejectionReason)"
                    class="px-8 py-3 rounded-xl bg-[#4DA1A9] text-white font-bold hover:bg-[#3a8c94] transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <Icon icon="solar:plain-bold" class="w-5 h-5" />
                    إرسال الرد
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>