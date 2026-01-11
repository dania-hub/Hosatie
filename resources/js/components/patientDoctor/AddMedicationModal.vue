<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[95vh] overflow-y-auto"
            dir="rtl"
        >
            <!-- Header -->
            <div class="bg-[#2E5077] px-8 py-5 flex justify-between items-center relative overflow-hidden sticky top-0 z-20">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-[#4DA1A9]/20 rounded-full -ml-12 -mb-12 blur-xl"></div>
                
                <h2 class="text-2xl font-bold text-white flex items-center gap-3 relative z-10">
                    <div class="p-2 bg-white/10 rounded-lg backdrop-blur-sm">
                        <Icon icon="solar:capsule-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    إضافة جرعة دوائية
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">

                <!-- Patient Info -->
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center">
                        <Icon icon="solar:user-bold-duotone" class="w-6 h-6 text-[#2E5077]" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">المريض المحدد</p>
                        <p class="text-lg font-bold text-[#2E5077]">{{ patient.nameDisplay || 'لا يوجد مريض محدد' }}</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                        <Icon icon="solar:medical-kit-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                        بيانات الدواء
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Category Select -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:filter-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الفئة
                            </label>
                            <div class="relative">
                                <select
                                    v-model="selectedCategory"
                                    @change="onCategoryChange"
                                    class="w-full h-11 px-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all appearance-none cursor-pointer"
                                    :disabled="isLoadingDrugs"
                                >
                                    <option value="">كل الفئات</option>
                                    <option v-for="(cat, index) in categories" :key="index" :value="cat">{{ cat }}</option>
                                </select>
                                <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                    <Icon icon="solar:alt-arrow-down-bold" class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        <!-- Drug Search -->
                        <div class="space-y-2 md:col-span-2 relative">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:magnifer-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                اسم الدواء
                            </label>
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="searchTermDrug" 
                                    @input="handleInput"
                                    @focus="onSearchFocus"
                                    @blur="hideResults"
                                    placeholder="ابحث عن دواء..."
                                    class="w-full h-11 px-4 pr-10 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all disabled:bg-gray-100 disabled:text-gray-400"
                                    :disabled="isLoadingDrugs || !!selectedDrugName"
                                    :readonly="!!selectedDrugName"
                                />
                                <button
                                    v-if="selectedDrugName"
                                    @click="clearSelectedDrug"
                                    type="button"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-600 hover:bg-red-50 p-1 rounded-full transition-all"
                                    title="مسح الدواء"
                                >
                                    <Icon icon="solar:close-circle-bold" class="w-5 h-5" />
                                </button>
                                <div v-else-if="isLoadingDrugs" class="absolute left-3 top-1/2 -translate-y-1/2">
                                    <Icon icon="svg-spinners:ring-resize" class="w-5 h-5 text-[#4DA1A9]" />
                                </div>
                            </div>

                            <!-- Search Results Dropdown -->
                            <div v-if="showResults && !isLoadingDrugs && filteredDrugs.length" class="absolute top-full left-0 right-0 z-30 mt-2 bg-white rounded-xl shadow-xl border border-gray-100 max-h-60 overflow-y-auto">
                                <ul class="py-2">
                                    <li
                                        v-for="drug in filteredDrugs"
                                        :key="drug.id"
                                        @mousedown="selectDrug(drug)"
                                        class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors border-b border-gray-50 last:border-0"
                                    >
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-[#2E5077]">{{ drug.name }}</span>
                                            <span v-if="drug.category" class="text-xs bg-[#EAF3F4] text-[#4DA1A9] px-2 py-1 rounded-lg font-medium">
                                                {{ drug.category }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                            <span>{{ drug.dosage || drug.strength || '' }}</span>
                                            <span v-if="drug.dosage || drug.strength" class="w-1 h-1 bg-gray-300 rounded-full"></span>
                                            <span class="text-[#4DA1A9] font-medium">
                                                {{ getDrugUnit(drug) }}
                                            </span>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div v-if="showResults && !isLoadingDrugs && filteredDrugs.length === 0" class="absolute top-full left-0 right-0 z-30 mt-2 bg-white rounded-xl shadow-xl border border-gray-100 p-4 text-center text-gray-500">
                                لا توجد نتائج مطابقة
                            </div>
                        </div>
                    </div>

                    <!-- Phasing Out Warning Alert -->
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div v-if="selectedDrugPhasingOut" class="p-4 bg-amber-50 border-2 border-amber-200 rounded-2xl flex items-start gap-3 animate-pulse-subtle">
                            <div class="p-2 bg-amber-100 rounded-lg">
                                <Icon icon="solar:danger-bold-duotone" class="w-6 h-6 text-amber-600" />
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-amber-800 text-sm">تنبيه هام: دواء قيد الإيقاف</h4>
                                <p class="text-amber-700 text-sm mt-1 leading-relaxed">
                                    {{ selectedDrugWarning || 'هذا الدواء في مرحلة الإيقاف التدريجي. يرجى التخطيط للانتقال لبديل.' }}
                                </p>
                            </div>
                        </div>
                    </Transition>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                        <!-- Quantity Input -->
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:calculator-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                الكمية اليومية (<span class="text-[#4DA1A9]">{{ quantityUnit }}</span>)
                            </label>
                            <input
                                type="number"
                                min="0"
                                :step="quantityUnit === 'مل' ? '0.5' : '1'"
                                v-model.number="dailyQuantity"
                                class="w-full h-11 px-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all disabled:bg-gray-100 disabled:text-gray-400"
                                placeholder="0"
                                :disabled="!selectedDrugName || isSaving"
                            />
                            <p v-if="quantityError" class="text-xs text-red-500 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ quantityError }}
                            </p>
                        </div>
                        
                        <!-- Add Button -->
                        <button
                            @click="addNewDrug"
                            :disabled="!isCurrentDrugValid || isSaving"
                            class="h-11 w-full rounded-xl font-bold flex items-center justify-center gap-2 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
                            :class="(!isCurrentDrugValid || isSaving) 
                                ? 'bg-gray-200 text-gray-400 cursor-not-allowed shadow-none' 
                                : 'bg-[#4DA1A9] text-white hover:bg-[#3a8c94] hover:-translate-y-0.5'"
                        >
                            <Icon v-if="!isSaving" icon="solar:add-circle-bold" class="w-5 h-5" />
                            <Icon v-else icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                            {{ isSaving ? 'جاري الإضافة...' : 'إضافة للقائمة' }}
                        </button>
                    </div>

                    <!-- Added Drugs List -->
                    <div v-if="dailyDosageList.length > 0" class="mt-8 bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:list-check-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                القائمة المختارة
                            </h3>
                            <span class="bg-[#2E5077] text-white text-xs px-2 py-1 rounded-lg">{{ dailyDosageList.length }}</span>
                        </div>
                        
                        <ul class="divide-y divide-gray-50">
                            <li v-for="(item, index) in dailyDosageList" :key="index" class="p-4 flex justify-between items-center hover:bg-gray-50/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 font-bold text-sm">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#2E5077] flex items-center gap-2">
                                            {{ item.name }}
                                            <span v-if="item.isPhasingOut" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200">
                                                قيد الإيقاف
                                            </span>
                                        </p>
                                        <p class="text-sm text-gray-500">{{ item.quantity }} {{ item.unit }} يومياً</p>
                                    </div>
                                </div>
                                <button 
                                    @click="removeItem(index)"
                                    :disabled="isSaving"
                                    class="text-red-400 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition-all"
                                >
                                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-5 h-5" />
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div v-else class="mt-8 py-8 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                        <Icon icon="solar:pill-broken" class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500 font-medium">لم يتم إضافة أي أدوية للقائمة بعد</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                    :disabled="isSaving"
                >
                    إلغاء
                </button>
                <button 
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm || isSaving"
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#2E5077]/20 flex items-center gap-2 transition-all duration-200"
                    :class="(!isReadyToConfirm || isSaving)
                        ? 'bg-gray-300 cursor-not-allowed shadow-none'
                        : 'bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] hover:bg-[#1a2f4d] hover:-translate-y-0.5'"
                >
                    <Icon v-if="isSaving" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                    <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
                    {{ isSaving ? 'جاري الحفظ...' : `تأكيد وحفظ (${totalItemsToConfirm})` }}
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showConfirmationModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="cancelConfirmation">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100 animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center space-y-4">
                <div class="w-16 h-16 bg-[#4DA1A9]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Icon icon="solar:question-circle-bold-duotone" class="w-10 h-10 text-[#4DA1A9]" />
                </div>
                <h3 class="text-xl font-bold text-[#2E5077]">تأكيد الإضافة</h3>
                <p class="text-gray-500 leading-relaxed">
                    هل أنت متأكد من إسناد **{{ totalItemsToConfirm }}** دواء للمريض؟
                </p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-100">
                <button 
                    @click="cancelConfirmation" 
                    :disabled="isSaving"
                    class="flex-1 px-4 py-2.5 rounded-xl text-gray-600 font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    مراجعة
                </button>
                <button 
                    @click="handleConfirmation" 
                    :disabled="isSaving"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-[#2E5077] to-[#4DA1A9] text-white font-medium hover:bg-[#1a2f4d] transition-colors duration-200 shadow-lg shadow-[#2E5077]/20"
                >
                    <Icon v-if="isSaving" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin inline ml-2" />
                    تأكيد نهائي
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios";

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'save']);

// ------------ حقن الـ API من المكون الرئيسي ------------
const api = axios.create({
    baseURL: '/api/doctor',
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// إضافة interceptor لإضافة التوكن تلقائيًا
api.interceptors.request.use(
    config => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

// ------------ بيانات وثوابت الحدود القصوى ------------
const MAX_PILL_QTY = 15;
const MAX_LIQUID_QTY = 30;

// ------------ حالة المكون (Component State) ------------
const categories = ref([]);
const filteredDrugs = ref([]);
const isLoadingDrugs = ref(false);
const isSaving = ref(false);

const selectedCategory = ref('');
const searchTermDrug = ref(''); 
const selectedDrugName = ref('');
const selectedDrugType = ref('');
const selectedDrugId = ref(null);
const selectedDrugUnit = ref('');
const selectedDrugForm = ref('');
const dailyQuantity = ref(null); 
const selectedDrugMaxMonthlyDose = ref(0);
const selectedDrugPhasingOut = ref(false);
const selectedDrugWarning = ref('');
const showResults = ref(false);
const dailyDosageList = ref([]); 

// حالة النافذة المنبثقة للتأكيد
const showConfirmationModal = ref(false);

// ------------ دوال API ------------
// جلب الفئات من الـ API
const fetchCategories = async () => {
    try {
        const response = await api.get('/drug-categories');
        // BaseApiController يُرجع البيانات في response.data.data
        categories.value = response.data.data || response.data;
    } catch (err) {
        console.error('خطأ في جلب الفئات:', err);
        // استخدام فئات افتراضية في حالة الخطأ
        categories.value = [];
    }
};

// جلب الأدوية من الـ API
const fetchDrugsData = async () => {
    isLoadingDrugs.value = true;
    try {
        const params = {};
        if (selectedCategory.value) {
            params.category = selectedCategory.value; // استخدام 'category' بدلاً من 'categoryId'
        }
        if (searchTermDrug.value) {
            params.search = searchTermDrug.value;
        }
        
        const response = await api.get('/drugs', { params });
        // BaseApiController يُرجع البيانات في response.data.data
        filteredDrugs.value = response.data.data || response.data;
    } catch (err) {
        console.error('خطأ في جلب الأدوية:', err);
        filteredDrugs.value = [];
    } finally {
        isLoadingDrugs.value = false;
    }
};

// جلب تفاصيل دواء محدد
const fetchDrugDetails = async (drugId) => {
    try {
        const response = await api.get(`/drugs/${drugId}`);
        // BaseApiController يُرجع البيانات في response.data.data
        return response.data.data || response.data;
    } catch (err) {
        console.error('خطأ في جلب تفاصيل الدواء:', err);
        return null;
    }
};

// ------------ وظائف المساعدة لتحديد الوحدة ------------
const getDrugUnit = (drug) => {
    // أولاً: استخدام وحدة القياس المباشرة من جدول الدواء
    if (drug.unit) {
        const unit = drug.unit.toLowerCase().trim();
        
        // تحويل الوحدات الشائعة إلى التنسيق المطلوب
        // للأدوية الصلبة (حبوب/أقراص)
        if (['قرص', 'حبة', 'tablet', 'capsule', 'pill', 'tab', 'cap'].includes(unit)) {
            return 'حبة';
        }
        // للأدوية السائلة
        if (['مل', 'ml', 'milliliter', 'millilitre', 'ملل', 'ملليلتر'].includes(unit)) {
            return 'مل';
        }
        // للحقن
        if (['أمبول', 'ampoule', 'ampule', 'vial', 'حقنة', 'amp'].includes(unit)) {
            return 'أمبول';
        }
        // للمراهم والكريمات
        if (['جرام', 'gram', 'gm', 'g', 'غرام'].includes(unit)) {
            return 'جرام';
        }
        // إذا كانت الوحدة موجودة ولكن غير معروفة، نستخدمها كما هي
        return drug.unit;
    }
    
    // ثانياً: استخدام form لتحديد الوحدة
    if (drug.form) {
        const form = drug.form.toLowerCase().trim();
        
        // للأدوية الصلبة
        if (['tablet', 'capsule', 'pill', 'tab', 'cap', 'قرص', 'حبة', 'كبسولة'].includes(form)) {
            return 'حبة';
        }
        // للأدوية السائلة
        if (['liquid', 'syrup', 'suspension', 'solution', 'elixir', 'drops', 'قطرة', 'سائل', 'شراب', 'معلق', 'محلول'].includes(form)) {
            return 'مل';
        }
        // للحقن
        if (['injection', 'ampoule', 'ampule', 'vial', 'amp', 'حقن', 'أمبول', 'حقنة'].includes(form)) {
            return 'أمبول';
        }
        // للمراهم والكريمات
        if (['ointment', 'cream', 'gel', 'lotion', 'مرهم', 'كريم', 'جل', 'لوشن'].includes(form)) {
            return 'جرام';
        }
    }
    
    // ثالثاً: البحث في اسم الدواء عن إشارات للنوع
    if (drug.name) {
        const name = drug.name.toLowerCase();
        
        // البحث عن كلمات تشير إلى سائل
        if (/syrup|suspension|solution|liquid|drops|شراب|معلق|محلول|قطرة|سائل|شرب/i.test(name)) {
            return 'مل';
        }
        // البحث عن كلمات تشير إلى حقن
        if (/injection|ampoule|vial|amp|حقن|أمبول|حقنة/i.test(name)) {
            return 'أمبول';
        }
        // البحث عن كلمات تشير إلى مرهم
        if (/ointment|cream|gel|lotion|مرهم|كريم|جل|لوشن/i.test(name)) {
            return 'جرام';
        }
    }
    
    // افتراضياً: حبة (للأدوية الصلبة)
    return 'حبة';
};

// ------------ Computed Properties ------------
const quantityUnit = computed(() => {
    // إذا كان لدينا وحدة محددة من الدواء، نستخدمها
    if (selectedDrugUnit.value) {
        return selectedDrugUnit.value;
    }
    
    // خلفية احتياطية بناءً على النوع (للتوافق مع الكود القديم)
    if (selectedDrugType.value === 'Tablet') return 'حبة';
    if (selectedDrugType.value === 'Liquid') return 'مل';
    if (selectedDrugType.value === 'Injection') return 'أمبول';
    return 'حبة';
});

const quantityInputId = computed(() => {
    return selectedDrugType.value === 'Tablet' ? 'pill-quantity-input' : 'liquid-quantity-input';
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "") return null;

    const numericQuantity = Number(quantity); 
    
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً.";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر.";
    }

    const unit = quantityUnit.value;
    const maxQty = unit === 'مل' ? MAX_LIQUID_QTY : MAX_PILL_QTY;
    const unitName = unit === 'مل' ? 'مل' : 'حبة/قرص';

    if (numericQuantity > maxQty) {
        return `لا يمكن أن تتجاوز الكمية اليومية ${maxQty} ${unitName}.`;
    }

    // التحقق من الحد الأقصى اليومي بناءً على الجرعة الشهرية القصوى
    const dailyLimit = selectedDrugMaxMonthlyDose.value / 30;
    if (dailyLimit > 0 && numericQuantity > dailyLimit) {
        return `الحد الأقصى المسموح به يومياً لهذا الدواء هو ${dailyLimit.toFixed(2)} ${unitName}.`;
    }
    
    return null;
});

const isCurrentDrugValid = computed(() => {
    const quantity = dailyQuantity.value;
    const isQuantityValid = quantity !== null && quantity > 0;
    const noQuantityError = quantityError.value === null;

    return selectedDrugName.value.length > 0 && selectedDrugType.value.length > 0 && isQuantityValid && noQuantityError;
});

const isReadyToConfirm = computed(() => {
    return dailyDosageList.value.length > 0 || isCurrentDrugValid.value;
});

const totalItemsToConfirm = computed(() => {
    return dailyDosageList.value.length + (isCurrentDrugValid.value ? 1 : 0);
});

// ------------ وظائف المساعدة ------------
const getCategoryName = (categoryName) => {
    // الفئات الآن عبارة عن نصوص بسيطة، نرجعها مباشرة
    return categoryName || 'غير معروف';
};

const clearForm = () => {
    selectedCategory.value = '';
    searchTermDrug.value = '';
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    selectedDrugId.value = null;
    selectedDrugUnit.value = '';
    selectedDrugForm.value = '';
    selectedDrugPhasingOut.value = false;
    selectedDrugWarning.value = '';
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    filteredDrugs.value = [];
    showResults.value = false;
};

// معالج تغيير الفئة
const onCategoryChange = () => {
    // مسح الدواء المحدد عند تغيير الفئة
    clearSelectedDrug();
    // جلب الأدوية حسب الفئة المحددة
    handleInput();
};

// عرض جميع الأدوية تلقائياً عند فتح النافذة
const showAllDrugs = async () => {
    showResults.value = true;
    await fetchDrugsData();
};

// مسح الدواء المحدد
const clearSelectedDrug = () => {
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    selectedDrugId.value = null;
    selectedDrugUnit.value = '';
    selectedDrugForm.value = '';
    selectedDrugMaxMonthlyDose.value = 0;
    selectedDrugPhasingOut.value = false;
    selectedDrugWarning.value = '';
    dailyQuantity.value = null;
    const hadCategory = !!selectedCategory.value;
    searchTermDrug.value = '';
    showResults.value = false;
    // إعادة جلب الأدوية بناءً على الفئة المحددة
    if (hadCategory) {
        handleInput();
    }
};

let debounceTimer;
const handleInput = () => {
    // إذا كان الدواء محدداً بالفعل، لا نقوم بمسحه عند البحث
    if (selectedDrugName.value) {
        return;
    }
    
    selectedDrugType.value = '';
    selectedDrugId.value = null;
    selectedDrugUnit.value = '';
    selectedDrugForm.value = '';
    selectedDrugMaxMonthlyDose.value = 0;
    dailyQuantity.value = null;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchDrugsData();
        showResults.value = true;
    }, 300);
};

const selectDrug = async (drug) => {
    searchTermDrug.value = drug.name;
    selectedDrugName.value = drug.name;
    selectedDrugId.value = drug.id;
    
    // جلب تفاصيل إضافية للدواء
    const drugDetails = await fetchDrugDetails(drug.id);
    if (drugDetails) {
        // استخدام البيانات من API
        selectedDrugForm.value = drugDetails.form || drug.form || '';
        // تحديد الوحدة باستخدام الدالة
        selectedDrugUnit.value = getDrugUnit(drugDetails);
        // تحديد النوع بناءً على form للتوافق
        if (drugDetails.form) {
            const form = drugDetails.form.toLowerCase().trim();
            if (['tablet', 'capsule', 'pill', 'tab', 'cap', 'قرص', 'حبة', 'كبسولة'].includes(form)) {
                selectedDrugType.value = 'Tablet';
            } else if (['liquid', 'syrup', 'suspension', 'solution', 'elixir', 'drops', 'قطرة', 'سائل', 'شراب', 'معلق', 'محلول'].includes(form)) {
                selectedDrugType.value = 'Liquid';
            } else if (['injection', 'ampoule', 'ampule', 'vial', 'amp', 'حقن', 'أمبول', 'حقنة'].includes(form)) {
                selectedDrugType.value = 'Injection';
            } else {
                selectedDrugType.value = 'Tablet';
            }
        } else {
            selectedDrugType.value = drug.type || 'Tablet';
        }

        // Set phasing out info from details
        selectedDrugPhasingOut.value = drugDetails.isPhasingOut || false;
        selectedDrugWarning.value = drugDetails.phasingOutWarning || '';
    } else {
        // استخدام البيانات من القائمة المباشرة
        selectedDrugForm.value = drug.form || '';
        selectedDrugUnit.value = getDrugUnit(drug);
        selectedDrugType.value = drug.type || 'Tablet';
        selectedDrugMaxMonthlyDose.value = drug.max_monthly_dose || 0;
        selectedDrugPhasingOut.value = drug.isPhasingOut || false;
        selectedDrugWarning.value = drug.phasingOutWarning || '';
    }

    // تأكد من تحديث الجرعة القصوى إذا كانت موجودة في تفاصيل الدواء
    if (drugDetails && drugDetails.max_monthly_dose) {
        selectedDrugMaxMonthlyDose.value = drugDetails.max_monthly_dose;
    }

    dailyQuantity.value = null;
    showResults.value = false;
};

const hideResults = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

const addNewDrug = () => {
    if (isCurrentDrugValid.value) {
        dailyDosageList.value.push({
            id: selectedDrugId.value,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
            form: selectedDrugForm.value,
            isPhasingOut: selectedDrugPhasingOut.value,
            phasingOutWarning: selectedDrugWarning.value,
        });

        searchTermDrug.value = '';
        selectedCategory.value = '';
        selectedDrugName.value = '';
        selectedDrugType.value = '';
        selectedDrugId.value = null;
        selectedDrugUnit.value = '';
        selectedDrugForm.value = '';
        dailyQuantity.value = null;
    }
};

const removeItem = (index) => {
    if (!isSaving.value) {
        dailyDosageList.value.splice(index, 1);
    }
};

// أيضًا عرض جميع الأدوية عند التركيز على حقل البحث
const onSearchFocus = () => {
    showResults.value = true;
    if (filteredDrugs.value.length === 0 && !isLoadingDrugs.value) {
        showAllDrugs();
    }
};

// ----------------------------------------------------
// وظيفة فتح النافذة المنبثقة للتأكيد
const openConfirmationModal = () => {
    if (isReadyToConfirm.value) {
        showConfirmationModal.value = true;
    }
};

// وظيفة التأكيد النهائي (الحفظ الفعلي)
const handleConfirmation = async () => {
    isSaving.value = true;
    
    try {
        // 1. إضافة الدواء الحالي للقائمة إذا كان صالحاً ولم تتم إضافته بعد
        if (isCurrentDrugValid.value) {
            dailyDosageList.value.push({
                id: selectedDrugId.value,
                name: selectedDrugName.value,
                quantity: dailyQuantity.value,
                unit: quantityUnit.value,
                type: selectedDrugType.value,
                form: selectedDrugForm.value,
                isPhasingOut: selectedDrugPhasingOut.value,
                phasingOutWarning: selectedDrugWarning.value,
            });
        }

        // 2. التحقق مرة أخيرة
        if (dailyDosageList.value.length > 0) {
            // تحويل البيانات إلى التنسيق المطلوب من الـ API
            const medicationsToSave = dailyDosageList.value.map(item => ({
                drugId: item.id,
                drugName: item.name,
                dosage: `${item.quantity} ${item.unit} يومياً`,
                dailyQuantity: item.quantity,
                unit: item.unit,
                type: item.type
            }));

            // إرسال البيانات إلى الـ API
            try {
                // يمكنك هنا إرسال البيانات إلى الـ API
                // مثال: await api.post(`/api/patients/${props.patient.fileNumber}/medications`, medicationsToSave);
                
                // في الوقت الحالي، نرسل البيانات إلى المكون الأب
                emit('save', medicationsToSave);
                
                clearForm();
                showConfirmationModal.value = false;
                emit('close');
            } catch (saveError) {
                console.error('خطأ في حفظ البيانات:', saveError);
                alert('حدث خطأ أثناء حفظ البيانات. يرجى المحاولة مرة أخرى.');
            }
        }
    } catch (error) {
        console.error('خطأ:', error);
        alert('حدث خطأ غير متوقع.');
    } finally {
        isSaving.value = false;
    }
};

// وظيفة إلغاء التأكيد
const cancelConfirmation = () => {
    if (!isSaving.value) {
        showConfirmationModal.value = false;
    }
};

// وظيفة الزر الأساسي
const confirmAddition = () => {
    openConfirmationModal();
};

const closeModal = () => {
    if (!isSaving.value) {
        clearForm();
        emit('close');
    }
};

// ----------------------------------------------------
// دورة حياة المكون
onMounted(async () => {
    await fetchCategories();
});

// مراقبة فتح النافذة
watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        // إعادة تعيين البيانات عند فتح النافذة
        clearForm();
        await fetchCategories();
    }
});
</script>
成功