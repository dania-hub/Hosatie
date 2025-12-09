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
            <!-- الهيدر بنفس التصميم -->
            <div
                class="flex justify-between items-center bg-[#F6F4F0] p-4 sm:p-6 border-b border-[#B8D7D9] sticky top-0 rounded-t-xl z-10"
            >
                <h3
                    id="modal-title"
                    class="text-xl font-extrabold text-[#2E5077] flex items-center"
                >
                    <Icon
                        icon="tabler:message-reply"
                        class="w-7 h-7 ml-3 text-[#4DA1A9]"
                    />
                    الرد على الطلب
                </h3>
            
                <button
                    @click="closeModal"
                    class="text-gray-400 hover:text-[#2E5077] transition duration-150 p-2 rounded-full hover:bg-[#B8D7D9]/30"
                >
                    <Icon icon="tabler:x" class="w-6 h-6" />
                </button>
            </div>

            <!-- المحتوى بنفس التنسيق والمساحات -->
            <div class="p-5 sm:px-6 sm:py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- بيانات المريض الأساسية - بنفس تصميم الكود السابق -->
                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:user" class="w-5 h-5 ml-2" />
                            بيانات المريض
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">رقم الملف:</span>
                                <span class="mr-2 text-gray-700 font-semibold">{{ requestData?.fileNumber || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">اسم المريض:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.patientName || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">نوع الطلب:</span>
                                <span class="mr-2 text-gray-700">{{ requestData?.requestType || 'غير محدد' }}</span>
                            </p>
                            
                            <p class="text-right">
                                <span class="font-bold text-[#2E5077]">التاريخ:</span>
                                <span class="mr-2 text-gray-700">{{ formatDate(requestData?.createdDate) || 'غير محدد' }}</span>
                            </p>
                        </div>

                        <!-- عرض المحتوى بنفس تصميم معلومات الطلب -->
                        <div v-if="requestData?.content" class="mt-4 pt-4 border-t border-gray-200">
                            <h3
                                class="text-md font-semibold text-[#4DA1A9] mb-2 flex items-center"
                            >
                                <Icon icon="tabler:clipboard" class="w-4 h-4 ml-2" />
                                المحتوى:
                            </h3>
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                <p class="text-gray-700 text-sm leading-relaxed">{{ requestData.content }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- حالة الطلب الحالية - بنفس تصميم الحالة في الكود السابق -->
                <div class="space-y-4">
                    <h3
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:clipboard-check" class="w-5 h-5 ml-2" />
                        حالة الطلب
                    </h3>
                    
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50 shadow-sm">
                        <p class="text-right mb-3">
                            <span class="font-bold text-[#2E5077]">الحالة الحالية:</span>
                            <span :class="getStatusClass(requestData?.requestStatus)" 
                                  class="mr-2 px-3 py-1 rounded-full text-xs font-semibold">
                                {{ requestData?.requestStatus || 'غير محدد' }}
                            </span>
                        </p>
                        
                        <p class="text-right">
                            <span class="font-bold text-[#2E5077]">آخر تحديث:</span>
                            <span class="mr-2 text-gray-700">{{ formatDate(requestData?.createdDate) || 'غير محدد' }}</span>
                        </p>
                    </div>
                </div>

                <!-- خيارات الرد - بنفس أبعاد الأقسام السابقة -->
                <div class="space-y-4">
                    <h3 
                        class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                    >
                        <Icon icon="tabler:message" class="w-5 h-5 ml-2" />
                        خيارات الرد
                    </h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <button
                            @click="selectAction('accept')"
                            :class="{
                                'bg-green-100 border-green-500 text-green-700 ring-2 ring-green-200': selectedAction === 'accept',
                                'bg-white border-gray-300 text-gray-700 hover:bg-gray-50': selectedAction !== 'accept'
                            }"
                            class="p-5 rounded-lg border-2 flex flex-col items-center justify-center transition-all duration-200 hover:shadow-md"
                        >
                            <Icon icon="tabler:check" class="w-10 h-10 mb-3" />
                            <span class="font-bold text-lg">قبول الطلب</span>
                            <span class="text-sm text-gray-600 mt-1">وإرسال الرد</span>
                        </button>
                        
                        <button
                            @click="selectAction('reject')"
                            :class="{
                                'bg-red-100 border-red-500 text-red-700 ring-2 ring-red-200': selectedAction === 'reject',
                                'bg-white border-gray-300 text-gray-700 hover:bg-gray-50': selectedAction !== 'reject'
                            }"
                            class="p-5 rounded-lg border-2 flex flex-col items-center justify-center transition-all duration-200 hover:shadow-md"
                        >
                            <Icon icon="tabler:x" class="w-10 h-10 mb-3" />
                            <span class="font-bold text-lg">رفض الطلب</span>
                            <span class="text-sm text-gray-600 mt-1">مع ذكر السبب</span>
                        </button>
                    </div>
                </div>

                <!-- حقل الرد - بنفس تصميم الرد في الكود السابق -->
                <div v-if="selectedAction === 'accept'" class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3 
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:message-circle" class="w-5 h-5 ml-2" />
                            الرد
                        </h3>
                        
                        <label class="block text-right">
                            <div class="mb-3 text-sm text-gray-600 bg-green-50 p-3 rounded-lg border border-green-100">
                                <p class="font-medium text-green-700 mb-1">نصائح للرد:</p>
                                <ul class="mr-4 space-y-1">
                                    <li class="flex items-center">
                                        <Icon icon="tabler:check" class="w-4 h-4 ml-1 text-green-600" />
                                        تفاصيل القبول والخطوات التالية
                                    </li>
                                    <li class="flex items-center">
                                        <Icon icon="tabler:check" class="w-4 h-4 ml-1 text-green-600" />
                                        المواعيد المقترحة
                                    </li>
                                    <li class="flex items-center">
                                        <Icon icon="tabler:check" class="w-4 h-4 ml-1 text-green-600" />
                                        التعليمات أو الشروط
                                    </li>
                                </ul>
                            </div>
                            <textarea
                                v-model="responseText"
                                rows="5"
                                class="w-full p-4 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                placeholder="أدخل ردك هنا..."
                            ></textarea>
                        </label>
                    </div>
                </div>

                <!-- سبب الرفض - بنفس تصميم سبب الرفض في الكود السابق -->
                <div v-if="selectedAction === 'reject'" class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3 
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:alert-circle" class="w-5 h-5 ml-2" />
                            سبب الرفض
                        </h3>
                        
                        <label class="block text-right">
                            <div class="mb-3 text-sm text-gray-600 bg-red-50 p-3 rounded-lg border border-red-100">
                                <p class="font-medium text-red-700 mb-1">ملاحظات هامة:</p>
                                <p class="text-red-600">
                                    اكتب السبب المنطقي لرفض الطلب. يجب أن يكون السبب واضحاً ويحترم المريض.
                                </p>
                            </div>
                            <textarea
                                v-model="rejectionReason"
                                rows="5"
                                class="w-full p-4 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                placeholder="أدخل سبب الرفض..."
                                required
                            ></textarea>
                        </label>
                    </div>

                    <!-- تحذير بنفس تصميم الكود السابق -->
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-start">
                            <Icon icon="tabler:alert-triangle" class="w-6 h-6 text-amber-600 ml-3 mt-0.5" />
                            <div class="text-right">
                                <p class="text-amber-800 text-sm font-semibold">تنبيه هام:</p>
                                <p class="text-amber-700 text-sm mt-1">
                                    سيتم إعلام المريض برفض طلبه. تأكد من أن سبب الرفض واضح ومقنع.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ملاحظات إضافية - بنفس تصميم الأقسام الأخرى -->
                <div class="space-y-4">
                    <div class="bg-white p-4 rounded-lg border border-[#B8D7D9]/50">
                        <h3 
                            class="text-lg font-semibold text-[#4DA1A9] border-b-2 border-dashed border-[#B8D7D9]/50 pb-2 mb-4 flex items-center"
                        >
                            <Icon icon="tabler:notes" class="w-5 h-5 ml-2" />
                            ملاحظات إضافية
                        </h3>
                        
                        <label class="block text-right">
                            <div class="mb-3 text-sm text-gray-600">
                                <p>أي معلومات إضافية أو تعليمات خاصة للمريض أو للمسؤولين الآخرين.</p>
                            </div>
                            <textarea
                                v-model="additionalNotes"
                                rows="4"
                                class="w-full p-4 border-2 border-[#B8D7D9] rounded-lg focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/30 transition-colors text-sm"
                                placeholder="أدخل أي ملاحظات إضافية..."
                            ></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <!-- الفوتر بنفس التصميم بالضبط -->
            <div
                class="p-5 sm:px-6 sm:py-4 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700"
            >
                <button
                    @click="closeModal"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-gray-700 dark:text-gray-200 bg-gray-200 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 font-semibold w-full sm:w-auto"
                >
                    إلغاء
                </button>
                
                <button
                    @click="submitResponse"
                    :disabled="!selectedAction || (selectedAction === 'reject' && !rejectionReason)"
                    :class="{
                        'bg-[#4DA1A9] hover:bg-[#3a8c94] cursor-pointer': selectedAction && (selectedAction !== 'reject' || rejectionReason),
                        'bg-gray-300 cursor-not-allowed': !selectedAction || (selectedAction === 'reject' && !rejectionReason)
                    }"
                    class="inline-flex h-11 items-center justify-center px-6 rounded-full transition-all duration-200 ease-in text-base cursor-pointer text-white border-2 border-transparent font-semibold w-full sm:w-auto"
                >
                    <Icon icon="tabler:send" class="w-5 h-5 ml-2" />
                    إرسال الرد
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

// دوال مساعدة بنفس الدوال في الكود السابق
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
            return 'bg-green-100 text-green-700';
        case 'قيد المراجعة':
            return 'bg-yellow-100 text-yellow-700';
        case 'مرفوض':
            return 'bg-red-100 text-red-700';
        default:
            return 'bg-gray-100 text-gray-700';
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

<style scoped>
.shadow-3xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
}

.max-h-\[70vh\] {
    max-height: 70vh;
}
</style>