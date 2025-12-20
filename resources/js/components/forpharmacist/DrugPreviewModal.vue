<!-- components/DrugPreviewModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity duration-300"
        @click.self="closeModal"
    >
        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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
                        <Icon icon="solar:eye-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    معاينة الدواء
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-6">
                <template v-if="drug.drugName">
                    <!-- Basic Info -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                                <Icon icon="solar:bottle-bold-duotone" class="w-7 h-7 text-[#2E5077]" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-[#2E5077] mb-1">{{ drug.drugName || "Glucophage" }}</h3>
                                <p class="text-gray-500 font-medium">{{ drug.scientificName || "Metformin Hydrochloride" }}</p>
                            </div>
                            <div class="bg-[#EAF3F4] text-[#4DA1A9] px-3 py-1 rounded-lg text-sm font-bold">
                                {{ drug.therapeuticClass || "أدوية علاج السكري" }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الشكل الصيدلاني</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.pharmaceuticalForm || "أقراص فموية" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الشركة المصنعة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.manufacturer || "Merck" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الدولة المصنعة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.mfgCountry || "فرنسا" }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Info -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-6">
                        <div class="space-y-2">
                            <h4 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:medical-kit-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                دواعي الاستعمال
                            </h4>
                            <p class="text-gray-600 text-sm leading-relaxed bg-gray-50 p-3 rounded-xl border border-gray-100">
                                {{ drug.indications || "يستخدم للتحكم في مستوى السكر المرتفع في الدم لدى مرضى السكري من النوع الثاني" }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <h4 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:clipboard-list-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                إرشادات الاستخدام
                            </h4>
                            <p class="text-gray-600 text-sm leading-relaxed bg-gray-50 p-3 rounded-xl border border-gray-100">
                                {{ drug.instructions || "يؤخذ عادة مع وجبات الطعام لتقليل اضطرابات المعدة. يجب اتباع تعليمات الطبيب بدقة بخصوص الجرعة" }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <h4 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="w-5 h-5 text-red-500" />
                                تحذيرات هامة
                            </h4>
                            <p class="text-gray-600 text-sm leading-relaxed bg-red-50 p-3 rounded-xl border border-red-100">
                                {{ drug.warnings || "يجب إبلاغ الطبيب في حال وجود أمراض في الكلى أو الكبد. لا يستخدم أثناء الحمل إلا باستشارة طبية" }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-50 rounded-full flex items-center justify-center text-orange-500">
                                <Icon icon="solar:calendar-date-bold-duotone" class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">تاريخ الانتهاء</p>
                                <p class="font-bold text-[#2E5077]">{{ drug.expiryDate || "2028/10/22" }}</p>
                            </div>
                        </div>
                        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center text-green-500">
                                <Icon icon="solar:refresh-circle-bold-duotone" class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">آخر تحديث</p>
                                <p class="font-bold text-[#2E5077]">{{ drug.lastUpdate || "2025/12/09" }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                    @click="closeModal"
                >
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";

defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    drug: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['close']);

const closeModal = () => {
    emit('close');
};
</script>