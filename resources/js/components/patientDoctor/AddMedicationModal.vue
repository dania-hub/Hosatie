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
            class="relative bg-[#F6F4F0] rounded-xl shadow-2xl w-full max-w-[95vw] sm:max-w-[700px] max-h-[95vh] sm:max-h-[90vh] overflow-y-auto transform transition-all duration-300 rtl"
            dir="rtl"
        >
            <div
                class="flex items-center justify-between p-4 sm:pr-6 sm:pl-6 pb-2.5 bg-[#F6F4F0] rounded-t-xl sticky top-0 z-10 border-b border-[#B8D7D9]"
            >
                <h2 class="text-xl sm:text-2xl font-bold text-[#2E5077] flex items-center pt-1.5">
                    <Icon icon="streamline:tablet-capsule" class="w-6 h-6 sm:w-9 sm:h-8 ml-2 text-[#2E5077]" />
                    نموذج إضافة جرعة يومية
                </h2>

                <button
                    @click="closeModal"
                    class="p-2 h-auto text-gray-500 hover:text-gray-900 cursor-pointer"
                >
                    <Icon icon="ri:close-large-fill" class="w-6 h-6 text-[#2E5077] mt-3" />
                </button>
            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 space-y-6">

                <div class="bg-white p-3 rounded-xl border border-[#B8D7D9] shadow-sm">
                    <p class="text-sm font-medium text-gray-600">المريض المحدد:</p>
                    <p class="text-lg font-bold text-[#2E5077]">{{ patient.nameDisplay || 'لا يوجد مريض محدد' }}</p>
                </div>

                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 flex items-center">
                        <Icon icon="tabler:medical-cross" class="w-5 h-5 ml-2" />
                        معلومات الدواء
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        
                        <div class="flex flex-col gap-1 sm:col-span-1">
                            <label for="drugCategory" class="text-right font-medium text-[#2E5077] pt-2">الفئة:</label>
                            <select
                                id="drugCategory"
                                v-model="selectedCategory"
                                @change="handleInput"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white cursor-pointer shadow-none text-gray-700"
                                :disabled="isLoadingDrugs"
                            >
                                <option value="">كل الفئات</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 relative sm:col-span-2">
                            <label for="drug-search" class="text-right font-medium text-[#2E5077] pt-2">اسم الدواء:</label>
                            <input
                                id="drug-search"
                                type="text"
                                v-model="searchTermDrug" 
                                @input="handleInput"
                                @focus="onSearchFocus"
                                @blur="hideResults"
                                placeholder="ابحث عن دواء أو اختر من القائمة"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                :disabled="selectedDrugName.length > 0 || isLoadingDrugs"
                            />

                            <!-- حالة التحميل -->
                            <div v-if="isLoadingDrugs && showResults" class="absolute top-full left-0 right-0 z-10 p-4 border border-[#4DA1A9] border-t-0 rounded-b-lg bg-white shadow-xl">
                                <div class="flex items-center justify-center">
                                    <Icon icon="eos-icons:loading" class="w-5 h-5 text-[#4DA1A9] animate-spin ml-2" />
                                    <p class="text-sm text-gray-600">جاري تحميل الأدوية...</p>
                                </div>
                            </div>

                            <ul v-if="showResults && !isLoadingDrugs && filteredDrugs.length" class="absolute top-full left-0 right-0 z-10 list-none p-0 m-0 border border-[#4DA1A9] border-t-0 rounded-b-lg max-h-52 overflow-y-auto bg-white shadow-xl">
                                <li
                                    v-for="drug in filteredDrugs"
                                    :key="drug.id"
                                    @mousedown="selectDrug(drug)"
                                    class="p-2.5 px-4 cursor-pointer border-b border-gray-100 text-sm text-[#2E5077] hover:bg-[#EAF3F4] transition-colors duration-200"
                                >
                                    <div class="flex justify-between items-center">
                                        <span class="font-medium">{{ drug.name }}</span>
                                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                            {{ getCategoryName(drug.categoryId) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1" v-if="drug.dosage">
                                        {{ drug.dosage }} - 
                                        <span class="font-medium" :class="drug.type === 'Tablet' ? 'text-blue-600' : 'text-green-600'">
                                            {{ drug.type === 'Tablet' ? 'أقراص' : 'سائل' }}
                                        </span>
                                    </div>
                                </li>
                            </ul>

                            <div v-if="showResults && !isLoadingDrugs && filteredDrugs.length === 0" class="absolute top-full left-0 right-0 z-10 p-4 border border-[#4DA1A9] border-t-0 rounded-b-lg bg-white shadow-xl">
                                <p class="text-sm text-gray-500 text-center">لا توجد أدوية مطابقة للبحث</p>
                            </div>

                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1 col-span-1">
                            <label :for="quantityInputId" class="text-right font-medium text-[#2E5077] pt-2">
                                الكمية اليومية (<span class="unit-text">{{ quantityUnit }}</span>):
                            </label>
                            <input
                                :id="quantityInputId"
                                type="number"
                                min="0"
                                step="0.5"
                                v-model.number="dailyQuantity"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                placeholder="أدخل الكمية"
                                :disabled="!selectedDrugName || isSaving"
                            />
                            <p v-if="quantityError" class="text-xs text-red-500 mt-1 font-semibold">{{ quantityError }}</p>

                        </div>
                        
                        <div class="pt-9">
                            <button
                                @click="addNewDrug"
                                :disabled="!isCurrentDrugValid || isSaving"
                                class="h-11 inline-flex items-center justify-center px-[25px] border-2 border-[#4DA1A9] rounded-[30px] transition-all duration-200 ease-in text-[15px] cursor-pointer text-[#4DA1A9] bg-white hover:bg-[#EAF3F4] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed w-full"
                            >
                                <Icon v-if="!isSaving" icon="tabler:plus" class="w-5 h-5 ml-1" />
                                <Icon v-else icon="eos-icons:loading" class="w-5 h-5 ml-1 animate-spin" />
                                {{ isSaving ? 'جاري الإضافة...' : 'إضافة للقائمة' }}
                            </button>
                        </div>
                    </div>

                    <div v-if="!selectedDrugName && searchTermDrug.length === 0 && showResults && !isLoadingDrugs" class="mt-2">
                        <p class="text-sm p-3 rounded-md border-r-4 border-[#4DA1A9] bg-[#EAF3F4] text-[#2E5077]">
                            💡 يمكنك البحث بالاسم أو اختيار فئة محددة للتصفية
                        </p>
                    </div>
                    
                    <div v-if="dailyDosageList.length > 0" class="mt-8">
                        <h3 class="text-lg font-semibold text-[#4DA1A9] border-b border-dashed border-[#B8D7D9] pb-1 mb-4">الأدوية المضافة للقائمة ({{ dailyDosageList.length }})</h3>
                        
                        <div class="p-4 border border-[#B8D7D9] rounded-md bg-[#F6F4F0] max-h-48 overflow-y-auto">
                            <ul class="list-none p-0 m-0">
                                <li v-for="(item, index) in dailyDosageList" :key="index" class="bg-white p-2.5 px-4 rounded-xl mb-2 flex justify-between items-center text-sm text-[#2E5077] font-medium border border-[#B8D7D9] shadow-sm">
                                    <span>
                                        **{{ item.name }}** - {{ item.quantity }} {{ item.unit }}
                                    </span>
                                    <span 
                                        class="text-red-600 cursor-pointer text-base opacity-90 hover:opacity-70 transition duration-200" 
                                        @click="removeItem(index)"
                                        :class="{'opacity-50 cursor-not-allowed': isSaving}"
                                        :disabled="isSaving"
                                    >
                                        ❌ حذف
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <p v-else class="text-center text-gray-500 py-2">لم يتم إضافة أي أدوية بعد.</p>
                </div>

            </div>

            <div class="p-4 sm:pr-6 sm:pl-6 pt-2 flex justify-end gap-3 sticky bottom-0 bg-[#F6F4F0] rounded-b-xl border-t border-[#B8D7D9]">
                
                <button
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm || isSaving"
                    class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-35 rounded-[30px] transition-all duration-200 ease-in relative overflow-hiddentext-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                >
                    <Icon v-if="isSaving" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
                    {{ isSaving ? 'جاري الحفظ...' : `تأكيد القائمة (${totalItemsToConfirm})` }}
                </button>
                
                <button 
                    @click="closeModal" 
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
                    :disabled="isSaving"
                >
                    إلغاء
                </button>
            </div>
        </div>
    </div>

    <div
        v-if="showConfirmationModal"
        class="fixed inset-0 z-[70] flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="cancelConfirmation"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm z-[75]"
        ></div>

        <div
            class="relative bg-white rounded-xl shadow-2xl w-full sm:w-[400px] max-w-[90vw] p-6 sm:p-8 text-center rtl z-[80] transform transition-all duration-300 scale-100"
        >
            <div class="flex flex-col items-center">
                <Icon
                    icon="tabler:alert-triangle-filled"
                    class="w-16 h-16 text-yellow-500 mb-4"
                />
                <p class="text-xl font-bold text-[#2E5077] mb-3">
                    تأكيد إسناد الجرعات الدوائية
                </p>
                <p class="text-base text-gray-700 mb-6">
                    هل أنت متأكد من رغبتك في إسناد **{{ totalItemsToConfirm }}** دواء/أدوية للمريض **{{ patient.nameDisplay || 'غير محدد' }}**؟
                </p>
                <div class="flex gap-4 justify-center w-full">
                    <button
                        @click="handleConfirmation"
                        :disabled="isSaving"
                        class="bg-[#4DA1A9] text-white font-semibold py-2 px-6 rounded-full hover:bg-[#3a8c94] transition-colors duration-200 disabled:bg-gray-300 disabled:cursor-not-allowed"
                    >
                        <Icon v-if="isSaving" icon="eos-icons:loading" class="w-5 h-5 ml-2 animate-spin" />
                        {{ isSaving ? 'جاري الحفظ...' : 'تأكيد وحفظ' }}
                    </button>
                    <button
                        @click="cancelConfirmation"
                        :disabled="isSaving"
                        class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200 disabled:bg-gray-200 disabled:cursor-not-allowed"
                    >
                        إلغاء
                    </button>
                </div>
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
const api = inject('api', axios.create({
    baseURL: 'https://api.your-domain.com',
    timeout: 10000
}));

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
const dailyQuantity = ref(null); 
const showResults = ref(false);
const dailyDosageList = ref([]); 

// حالة النافذة المنبثقة للتأكيد
const showConfirmationModal = ref(false);

// ------------ دوال API ------------
// جلب الفئات من الـ API
const fetchCategories = async () => {
    try {
        const response = await api.get('/api/drugs/categories');
        categories.value = response.data;
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
            params.categoryId = selectedCategory.value;
        }
        if (searchTermDrug.value) {
            params.search = searchTermDrug.value;
        }
        
        const response = await api.get('/api/drugs', { params });
        filteredDrugs.value = response.data;
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
        const response = await api.get(`/api/drugs/${drugId}`);
        return response.data;
    } catch (err) {
        console.error('خطأ في جلب تفاصيل الدواء:', err);
        return null;
    }
};

// ------------ Computed Properties ------------
const quantityUnit = computed(() => {
    if (selectedDrugType.value === 'Tablet') return 'حبة/قرص';
    if (selectedDrugType.value === 'Liquid') return 'مل';
    if (selectedDrugType.value === 'Injection') return 'وحدة';
    return 'وحدة';
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

    if (selectedDrugType.value === 'Tablet') {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_PILL_QTY} حبة/قرص.`;
        }
    } else if (selectedDrugType.value === 'Liquid') {
        if (numericQuantity > MAX_LIQUID_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_LIQUID_QTY} مل.`;
        }
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
const getCategoryName = (categoryId) => {
    const category = categories.value.find(cat => cat.id === categoryId);
    return category ? category.name : 'غير معروف';
};

const clearForm = () => {
    selectedCategory.value = '';
    searchTermDrug.value = '';
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    selectedDrugId.value = null;
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    filteredDrugs.value = [];
    showResults.value = false;
};

// عرض جميع الأدوية تلقائياً عند فتح النافذة
const showAllDrugs = async () => {
    showResults.value = true;
    await fetchDrugsData();
};

let debounceTimer;
const handleInput = () => {
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    selectedDrugId.value = null;
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
        selectedDrugType.value = drugDetails.type || 'Tablet';
    } else {
        selectedDrugType.value = drug.type || 'Tablet';
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
        });

        searchTermDrug.value = '';
        selectedCategory.value = '';
        selectedDrugName.value = '';
        selectedDrugType.value = '';
        selectedDrugId.value = null;
        dailyQuantity.value = null;
        
        // إعادة عرض جميع الأدوية بعد الإضافة
        showAllDrugs();
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
    await showAllDrugs();
});

// مراقبة فتح النافذة
watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        // إعادة تعيين البيانات عند فتح النافذة
        clearForm();
        await fetchCategories();
        await showAllDrugs();
    }
});
</script>