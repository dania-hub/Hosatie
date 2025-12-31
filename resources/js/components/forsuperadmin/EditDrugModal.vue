<!-- components/forsuperadmin/EditDrugModal.vue -->
<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity duration-300"
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
                        <Icon icon="solar:pen-new-square-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    تعديل بيانات الدواء
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-8 space-y-6">
                <!-- Basic Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4">المعلومات الأساسية</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- اسم الدواء -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                اسم الدواء <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.name"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل اسم الدواء"
                            />
                        </div>

                        <!-- الاسم العلمي -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الاسم العلمي <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.generic_name"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الاسم العلمي"
                            />
                        </div>

                        <!-- التركيز -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                التركيز <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.strength"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="مثال: 500mg"
                            />
                        </div>

                        <!-- الشكل الصيدلاني -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الشكل الصيدلاني <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.form"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="مثال: أقراص، كبسولات"
                            />
                        </div>

                        <!-- الفئة العلاجية -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الفئة العلاجية <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.category"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الفئة العلاجية"
                            />
                        </div>

                        <!-- الوحدة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الوحدة <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.unit"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="مثال: قرص، مل"
                            />
                        </div>

                        <!-- الجرعة الشهرية القصوى -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الجرعة الشهرية القصوى <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.max_monthly_dose"
                                type="number"
                                required
                                min="1"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الجرعة الشهرية القصوى"
                            />
                        </div>

                        <!-- الحالة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الحالة <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="formData.status"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            >
                                <option value="">اختر الحالة</option>
                                <option value="متوفر">متوفر</option>
                                <option value="غير متوفر">غير متوفر</option>
                                <option value="تم الصرف">تم الصرف</option>
                            </select>
                        </div>

                        <!-- الشركة المصنعة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الشركة المصنعة <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.manufacturer"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل اسم الشركة المصنعة"
                            />
                        </div>

                        <!-- الدولة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الدولة <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.country"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الدولة"
                            />
                        </div>

                        <!-- نوع الاستخدام -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                نوع الاستخدام <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.utilization_type"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="مثال: مزمن، حاد"
                            />
                        </div>

                        <!-- تاريخ الانتهاء -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                تاريخ الانتهاء <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.expiry_date"
                                type="date"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            />
                        </div>
                    </div>
                </div>

                <!-- Medical Info -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                    <h3 class="text-lg font-bold text-[#2E5077] mb-4">المعلومات الطبية</h3>
                    
                    <!-- دواعي الاستعمال -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            دواعي الاستعمال <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="formData.indications"
                            rows="4"
                            required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            placeholder="أدخل دواعي الاستعمال..."
                        ></textarea>
                    </div>

                    <!-- تحذيرات هامة -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            تحذيرات هامة <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="formData.warnings"
                            rows="4"
                            required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            placeholder="أدخل التحذيرات الهامة..."
                        ></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                    <button
                        type="button"
                        class="px-6 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                        @click="closeModal"
                        :disabled="isSubmitting"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-[#4DA1A9] text-white font-medium hover:bg-[#3d8c94] transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting">جاري التحديث...</span>
                        <span v-else>تحديث البيانات</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from "vue";
import { Icon } from "@iconify/vue";

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    drug: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['close', 'update-drug']);

const formData = ref({});
const isSubmitting = ref(false);

// عند تغيير الدواء أو فتح النافذة، نسخ البيانات
watch(() => [props.isOpen, props.drug], ([isOpen, drug]) => {
    if (isOpen && drug) {
        formData.value = {
            id: drug.id,
            name: drug.name || drug.drugName || '',
            generic_name: drug.generic_name || drug.genericName || drug.scientificName || '',
            strength: drug.strength || '',
            form: drug.form || '',
            category: drug.category || drug.therapeuticClass || '',
            unit: drug.unit || '',
            max_monthly_dose: drug.max_monthly_dose || '',
            status: drug.status || '',
            manufacturer: drug.manufacturer || '',
            country: drug.country || '',
            utilization_type: drug.utilization_type || '',
            warnings: drug.warnings || '',
            indications: drug.indications || '',
            expiry_date: drug.expiry_date || drug.expiryDate || ''
        };
    }
}, { immediate: true });

// إغلاق النافذة
const closeModal = () => {
    if (!isSubmitting.value) {
        emit('close');
    }
};

// إرسال النموذج
const submitForm = async () => {
    isSubmitting.value = true;
    
    try {
        emit('update-drug', formData.value);
        emit('close');
    } catch (error) {
        console.error('Error updating drug:', error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
