<!-- components/forsuperadmin/AddDrugModal.vue -->
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
                        <Icon icon="solar:add-circle-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    إضافة دواء جديد
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
                                v-model="formData.drugName"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل اسم الدواء"
                            />
                        </div>

                        <!-- الرمز -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                رمز الدواء <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.drugCode"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الرمز الفريد"
                            />
                        </div>

                        <!-- الاسم العلمي -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الاسم العلمي
                            </label>
                            <input
                                v-model="formData.scientificName"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل الاسم العلمي"
                            />
                        </div>

                        <!-- الفئة العلاجية -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الفئة العلاجية
                            </label>
                            <select
                                v-model="formData.therapeuticClass"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            >
                                <option value="">اختر الفئة العلاجية</option>
                                <option v-for="category in categories" :key="category.id" :value="category.name">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <!-- الشكل الصيدلاني -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الشكل الصيدلاني
                            </label>
                            <select
                                v-model="formData.pharmaceuticalForm"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            >
                                <option value="">اختر الشكل الصيدلاني</option>
                                <option v-for="form in pharmaceuticalForms" :key="form" :value="form">
                                    {{ form }}
                                </option>
                            </select>
                        </div>

                        <!-- الشركة المصنعة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الشركة المصنعة
                            </label>
                            <input
                                v-model="formData.manufacturer"
                                type="text"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                                placeholder="أدخل اسم الشركة المصنعة"
                            />
                        </div>

                        <!-- الدولة المصنعة -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                الدولة المصنعة
                            </label>
                            <select
                                v-model="formData.mfgCountry"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            >
                                <option value="">اختر الدولة المصنعة</option>
                                <option v-for="country in countries" :key="country" :value="country">
                                    {{ country }}
                                </option>
                            </select>
                        </div>

                        <!-- تاريخ الانتهاء -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                تاريخ الانتهاء <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="formData.expiryDate"
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
                            دواعي الاستعمال
                        </label>
                        <textarea
                            v-model="formData.indications"
                            rows="3"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            placeholder="أدخل دواعي الاستعمال..."
                        ></textarea>
                    </div>

                    <!-- إرشادات الاستخدام -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            إرشادات الاستخدام
                        </label>
                        <textarea
                            v-model="formData.instructions"
                            rows="3"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#4DA1A9] focus:border-transparent transition-all duration-200"
                            placeholder="أدخل إرشادات الاستخدام..."
                        ></textarea>
                    </div>

                    <!-- تحذيرات هامة -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            تحذيرات هامة
                        </label>
                        <textarea
                            v-model="formData.warnings"
                            rows="3"
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
                        <span v-if="isSubmitting">جاري الحفظ...</span>
                        <span v-else>حفظ الدواء</span>
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
    categories: {
        type: Array,
        default: () => []
    },
    pharmaceuticalForms: {
        type: Array,
        default: () => []
    },
    countries: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'add-drug']);

// تهيئة بيانات النموذج
const formData = ref({
    drugName: '',
    drugCode: '',
    scientificName: '',
    therapeuticClass: '',
    pharmaceuticalForm: '',
    manufacturer: '',
    mfgCountry: '',
    expiryDate: '',
    quantity: 0,
    indications: '',
    instructions: '',
    warnings: ''
});

const isSubmitting = ref(false);

// إغلاق النافذة
const closeModal = () => {
    if (!isSubmitting.value) {
        resetForm();
        emit('close');
    }
};

// إعادة تعيين النموذج
const resetForm = () => {
    formData.value = {
        drugName: '',
        drugCode: '',
        scientificName: '',
        therapeuticClass: '',
        pharmaceuticalForm: '',
        manufacturer: '',
        mfgCountry: '',
        expiryDate: '',
        quantity: 0,
        indications: '',
        instructions: '',
        warnings: ''
    };
    isSubmitting.value = false;
};

// عند فتح النافذة، إعادة تعيين البيانات
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        resetForm();
    }
});

// إرسال النموذج
const submitForm = async () => {
    isSubmitting.value = true;
    
    try {
        // هنا يمكنك إضافة منطق التحقق من البيانات قبل الإرسال
        emit('add-drug', formData.value);
        resetForm();
        emit('close');
    } catch (error) {
        console.error('Error submitting form:', error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>