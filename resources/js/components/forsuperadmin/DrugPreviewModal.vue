<!-- components/DrugPreviewModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
        @click.self="closeModal"
    >
        <div
            class="bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[90vh] overflow-y-auto"
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
                <template v-if="drug.name || drug.drugName">
                    <!-- Basic Info -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                                <Icon icon="solar:bottle-bold-duotone" class="w-7 h-7 text-[#2E5077]" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-[#2E5077] mb-1">{{ drug.name || drug.drugName || "غير محدد" }}</h3>
                                <p class="text-gray-500 font-medium">{{ drug.generic_name || drug.genericName || drug.scientificName || "غير محدد" }}</p>
                            </div>
                            <div class="bg-[#EAF3F4] text-[#4DA1A9] px-3 py-1 rounded-lg text-sm font-bold">
                                {{ drug.category || drug.therapeuticClass || "غير محدد" }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 pt-4 border-t border-gray-100">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">التركيز</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.strength || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الشكل الصيدلاني</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.form || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الوحدة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.unit || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الجرعة الشهرية القصوى</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.max_monthly_dose || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الحالة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.status || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الشركة المصنعة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.manufacturer || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">الدولة</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.country || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">نوع الاستخدام</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.utilization_type || "غير محدد" }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-1">تاريخ الانتهاء</p>
                                <p class="font-semibold text-[#2E5077]">{{ drug.expiry_date || drug.expiryDate || "غير محدد" }}</p>
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
                                {{ drug.indications || "غير محدد" }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <h4 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:danger-triangle-bold-duotone" class="w-5 h-5 text-red-500" />
                                تحذيرات هامة
                            </h4>
                            <p class="text-gray-600 text-sm leading-relaxed bg-red-50 p-3 rounded-xl border border-red-100">
                                {{ drug.warnings || "غير محدد" }}
                            </p>
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
