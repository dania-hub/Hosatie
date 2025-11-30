<!-- components/DrugPreviewModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/40 bg-opacity-50 transition-opacity duration-300"
        @click.self="closeModal"
    >
        <div
            class="bg-white rounded-xl shadow-2xl p-0 w-11/12 md:w-1/2 max-w-2xl transition-transform duration-300 transform scale-100 overflow-hidden"
            dir="rtl"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="bg-[#F6F4F0] p-4 border-b border-[#4DA1A9] flex justify-between items-center"
            >
                <h3 class="text-xl font-bold text-[#2E5077] flex items-center">
                    <Icon
                        icon="fluent:check-20-regular"
                        class="w-8 h-8 inline-block mr-2 mt-2 text-[#2E5077]"
                    />
                    نموذج معاينة الدواء
                </h3>
                <button
                    @click="closeModal"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon
                        icon="ri:close-large-fill"
                        class="w-6 h-6 text-[#2E5077] mt-3"
                    />
                </button>
            </div>

            <div class="p-0 text-right">
                <template v-if="drug.drugName">
                    <div
                        class="px-4 py-4 border-b bg-[#F6F4F0] border-[#4DA1A9]"
                    >
                        <div class="grid grid-cols-12 mb-2">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >اسم الدواء</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.drugName || "Glucophage" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12 mb-2">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >الاسم العلمي</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.scientificName || "Metformin Hydrochloride" }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="px-4 py-3 border-b bg-[#F6F4F0] border-[#4DA1A9]"
                    >
                        <div class="grid grid-cols-12">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >الفئة العلاجية</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.therapeuticClass || "أدوية علاج السكري" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12 mb-2">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >الشكل الصيدلاني</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.pharmaceuticalForm || "أقراص فموية" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12 mb-2">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >الشركة المصنعة</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.manufacturer || "Merck" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >الدولة المصنعة</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c]"
                                >{{ drug.mfgCountry || "فرنسا" }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="px-4 py-3 border-b bg-[#F6F4F0] border-[#4DA1A9]"
                    >
                        <div class="grid grid-cols-12 mb-4">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >دواعي الاستعمال</span
                            >
                            <span
                                class="col-span-8 text-[#55595c] text-sm leading-relaxed"
                                >{{ drug.indications || "يستخدم للتحكم في مستوى السكر المرتفع في الدم لدى مرضى السكري من النوع الثاني" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12 mb-4">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >إرشادات الاستخدام</span
                            >
                            <span
                                class="col-span-8 text-[#55595c] text-sm leading-relaxed"
                                >{{ drug.instructions || "يؤخذ عادة مع وجبات الطعام لتقليل اضطرابات المعدة. يجب اتباع تعليمات الطبيب بدقة بخصوص الجرعة" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >تحذيرات هامة</span
                            >
                            <span
                                class="col-span-8 font-normal text-[#55595c] text-sm leading-relaxed"
                                >{{ drug.warnings || "يجب إبلاغ الطبيب في حال وجود أمراض في الكلى أو الكبد. لا يستخدم أثناء الحمل إلا باستشارة طبية" }}</span
                            >
                        </div>
                    </div>

                    <div class="px-4 bg-[#F6F4F0] py-3">
                        <div class="grid grid-cols-12 mb-2">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >تاريخ إنتهاء الصلاحية</span
                            >
                            <span
                                class="col-span-8 font-bold text-[text-[#55595c]]"
                                >{{ drug.expiryDate || "2028/10/22" }}</span
                            >
                        </div>

                        <div class="grid grid-cols-12">
                            <span
                                class="col-span-4 font-semibold text-[#2E5077]"
                                >آخر تحديث</span
                            >
                            <span
                                class="col-span-8 font-normal text-[text-[#55595c]]"
                                >{{ drug.lastUpdate || "2025/12/09" }}</span
                            >
                        </div>
                    </div>
                </template>
            </div>

            <div class="p-4 pt-3 flex justify-end bg-[#F6F4F0]">
                <button
                    class="inline-flex items-center px-[27px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] h-10 cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                    @click="closeModal"
                >
                    إلغاء
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