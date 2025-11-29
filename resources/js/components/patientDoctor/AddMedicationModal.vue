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
                                :disabled="selectedDrugName.length > 0"
                            />

                            <ul v-if="showResults && uniqueFilteredDrugs.length" class="absolute top-full left-0 right-0 z-10 list-none p-0 m-0 border border-[#4DA1A9] border-t-0 rounded-b-lg max-h-52 overflow-y-auto bg-white shadow-xl">
                                <li
                                    v-for="drug in uniqueFilteredDrugs"
                                    :key="drug.id"
                                    @mousedown="selectDrug(drug)"
                                    class="p-2.5 px-4 cursor-pointer border-b border-gray-100 text-sm text-[#2E5077] hover:bg-[#EAF3F4] transition-colors duration-200"
                                >
                                    <div class="flex justify-between items-center">
                                        <span class="font-medium">{{ drug.name }}</span>
                                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                            {{ categories.find(cat => cat.id === drug.categoryId)?.name }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1" v-if="drug.mostCommonDosage">
                                        {{ drug.mostCommonDosage }} - 
                                        <span class="font-medium" :class="drug.type === 'Tablet' ? 'text-blue-600' : 'text-green-600'">
                                            {{ drug.type === 'Tablet' ? 'أقراص' : 'سائل' }}
                                        </span>
                                    </div>
                                </li>
                            </ul>

                            <div v-if="showResults && uniqueFilteredDrugs.length === 0" class="absolute top-full left-0 right-0 z-10 p-4 border border-[#4DA1A9] border-t-0 rounded-b-lg bg-white shadow-xl">
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
                                v-model.number="dailyQuantity"
                                class="h-11 p-2.5 px-4 border border-[#B8D7D9] rounded-2xl text-base w-full transition duration-200 focus:border-[#4DA1A9] focus:ring-1 focus:ring-[#4DA1A9] bg-white shadow-none disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed"
                                placeholder="أدخل الكمية"
                                :disabled="!selectedDrugName"
                            />
                            <p v-if="quantityError" class="text-xs text-red-500 mt-1 font-semibold">{{ quantityError }}</p>

                        </div>
                        
                        <div class="pt-9">
                            <button
                                @click="addNewDrug"
                                :disabled="!isCurrentDrugValid"
                                class="h-11 inline-flex items-center justify-center px-[25px] border-2 border-[#4DA1A9] rounded-[30px] transition-all duration-200 ease-in text-[15px] cursor-pointer text-[#4DA1A9] bg-white hover:bg-[#EAF3F4] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed w-full"
                            >
                                <Icon icon="tabler:plus" class="w-5 h-5 ml-1" />
                                إضافة للقائمة
                            </button>
                        </div>
                    </div>

                    <div v-if="!selectedDrugName && searchTermDrug.length === 0 && showResults" class="mt-2">
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
                                    <span class="text-red-600 cursor-pointer text-base opacity-90 hover:opacity-70 transition duration-200" @click="removeItem(index)">❌ حذف</span>
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
                    :disabled="!isReadyToConfirm"
                    class="inline-flex items-center px-[11px] py-[9px] border-2 border-[#ffffff8d] h-11 w-35 rounded-[30px] transition-all duration-200 ease-in relative overflow-hiddentext-[15px] cursor-pointer text-white z-[1] bg-[#4DA1A9] hover:border hover:border-[#a8a8a8] hover:bg-[#5e8c90f9] disabled:bg-gray-300 disabled:border-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                >
                    تأكيد القائمة ({{ totalItemsToConfirm }})
                </button>
                
                <button 
                    @click="closeModal" 
                    class="inline-flex h-11 items-center px-[25px] border-2 border-[#b7b9bb] rounded-[30px] transition-all duration-200 ease-in relative overflow-hidden text-[15px] cursor-pointer text-[#374151] z-[1] bg-[#e5e7eb] hover:border-[#a8a8a8] hover:bg-[#b7b9bb]"
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
                        class="bg-[#4DA1A9] text-white font-semibold py-2 px-6 rounded-full hover:bg-[#3a8c94] transition-colors duration-200"
                    >
                        تأكيد وحفظ
                    </button>
                    <button
                        @click="cancelConfirmation"
                        class="bg-gray-300 text-[#374151] font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-200"
                    >
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import axios from "axios"; // 1. استيراد axios

const props = defineProps({
    isOpen: Boolean,
    patient: Object
});

const emit = defineEmits(['close', 'save']);

// ------------ بيانات وثوابت الحدود القصوى ------------
const MAX_PILL_QTY = 15;
const MAX_LIQUID_QTY = 30;
//الفئات 
const categories = ref([]);
//الادوية
const allDrugsData = [];

// ------------ حالة المكون (Component State) ------------
const selectedCategory = ref('');
const searchTermDrug = ref(''); 
const filteredDrugs = ref([]);
const selectedDrugName = ref('');
const selectedDrugType = ref('');
const dailyQuantity = ref(null); 
const showResults = ref(false);
const dailyDosageList = ref([]); 

// حالة النافذة المنبثقة للتأكيد
const showConfirmationModal = ref(false);

// ------------ Computed Properties (بدون تغيير) ------------
const uniqueFilteredDrugs = computed(() => {
    const uniqueNames = new Set();
    const uniqueDrugs = [];

    filteredDrugs.value.forEach(drug => {
        if (!uniqueNames.has(drug.name)) {
            uniqueNames.add(drug.name);
            const commonDosage = allDrugsData.find(d => d.name === drug.name)?.dosage;
            uniqueDrugs.push({ ...drug, mostCommonDosage: commonDosage });
        }
    });
    return uniqueDrugs.slice(0, 15); // زيادة عدد النتائج المعروضة
});

const quantityUnit = computed(() => {
    if (selectedDrugType.value === 'Tablet') return 'حبة/قرص';
    if (selectedDrugType.value === 'Liquid') return 'مل';
    return 'وحدة';
});

const quantityInputId = computed(() => {
    return selectedDrugType.value === 'Tablet' ? 'pill-quantity-input' : 'liquid-quantity-input';
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "") return null;

    // هنا يجب تحويل القيمة إلى رقم، حيث أننا أزلنا .number من v-model
    const numericQuantity = Number(quantity); 
    
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً.";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر.";
    }

    if (selectedDrugType.value === 'Tablet') {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_PILL_QTY} حبة/قرص.`;
        }
        // ✅ تم إزالة شرط !Number.isInteger(numericQuantity) للسماح بالكميات العشرية للأقراص.
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

// ------------ وظائف المنطق ------------
const clearForm = () => {
    selectedCategory.value = '';
    searchTermDrug.value = '';
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    //filteredDrugs.value = [];
       filteredDrugs.value = allDrugsData.value; // إعادة التعيين إلى القائمة الكاملة
};

// 4. تعديل دالة البحث لتستخدم البيانات المحملة
const fetchDrugsData = () => {
    let results = allDrugsData.value.filter(drug => {
        const categoryMatch = !selectedCategory.value || drug.categoryId === selectedCategory.value;
        const searchMatch = !searchTermDrug.value || drug.name.toLowerCase().includes(searchTermDrug.value.toLowerCase());
        return categoryMatch && searchMatch;
    });
    filteredDrugs.value = results;
};

// عرض جميع الأدوية تلقائياً عند فتح النافذة
const showAllDrugs = () => {
    filteredDrugs.value = allDrugsData.value;
    showResults.value = true;
};

let debounceTimer;
const handleInput = () => {
    selectedDrugName.value = '';
    selectedDrugType.value = '';
    dailyQuantity.value = null;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (searchTermDrug.value.length > 0 || selectedCategory.value) {
            fetchDrugsData();
            showResults.value = true;
        } else {
            // إذا كان حقل البحث فارغاً ولم يتم اختيار فئة، اعرض جميع الأدوية
            showAllDrugs();
        }
    }, 300);
};

const selectDrug = (drug) => {
    searchTermDrug.value = drug.name;
    selectedDrugName.value = drug.name;

    const fullDrugData = allDrugsData.find(d => d.name === drug.name);
    const newDrugType = fullDrugData ? fullDrugData.type : '';
    selectedDrugType.value = newDrugType;

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
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
        });

        searchTermDrug.value = '';
        selectedCategory.value = '';
        selectedDrugName.value = '';
        selectedDrugType.value = '';
        dailyQuantity.value = null;
        
        // إعادة عرض جميع الأدوية بعد الإضافة
        showAllDrugs();
    }
};

const removeItem = (index) => {
    dailyDosageList.value.splice(index, 1);
};


// ----------------------------------------------------
// وظيفة فتح النافذة المنبثقة للتأكيد (بدلاً من الحفظ المباشر)
const openConfirmationModal = () => {
    if (isReadyToConfirm.value) {
        showConfirmationModal.value = true;
    }
};

// وظيفة التأكيد النهائي (الحفظ الفعلي)
const handleConfirmation = () => {
    // 1. إضافة الدواء الحالي للقائمة إذا كان صالحاً ولم تتم إضافته بعد
    if (isCurrentDrugValid.value) {
        dailyDosageList.value.push({
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
        });
    }

    // 2. التحقق مرة أخيرة وإصدار الحدث
    if (dailyDosageList.value.length > 0) {
        emit('save', dailyDosageList.value);
        clearForm();
        showConfirmationModal.value = false; // إغلاق نافذة التأكيد
        emit('close'); // إغلاق النافذة الرئيسية
    }
};

// وظيفة إلغاء التأكيد وإغلاق النافذة المنبثقة
const cancelConfirmation = () => {
    showConfirmationModal.value = false;
};

// وظيفة الزر الأساسي (تم تعديلها لاستدعاء فتح النافذة المنبثقة)
const confirmAddition = () => {
    openConfirmationModal();
};

const closeModal = () => {
    clearForm();
    emit('close');
};

// ----------------------------------------------------


// إعادة تعيين النموذج وعرض جميع الأدوية عند فتح النافذة
watch(() => props.isOpen, (newVal) => {
    if (newVal) {
        clearForm();
       // جلب البيانات فقط إذا لم تكن موجودة بالفعل
        if (categories.value.length === 0) {
            fetchCategories();
        }
        if (allDrugsData.value.length === 0) {
            fetchAllDrugs();
        } else {
            // إذا كانت البيانات موجودة، فقط اعرضها
            setTimeout(() => {
                showAllDrugs();
            }, 100);
        }
    }
});

// أيضًا عرض جميع الأدوية عند التركيز على حقل البحث
const onSearchFocus = () => {
    showResults.value = true;
    if (filteredDrugs.value.length === 0) {
        showAllDrugs();
    }
};
</script>