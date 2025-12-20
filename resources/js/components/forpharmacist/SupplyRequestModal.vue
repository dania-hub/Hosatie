<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4"
    >
        <div
            @click="closeModal"
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
        ></div>

        <div
            class="relative bg-[#F2F2F2] rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden transform transition-all scale-100 max-h-[95vh] overflow-y-auto"
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
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-7 h-7 text-[#4DA1A9]" />
                    </div>
                    طلب توريد أدوية
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
                <!-- Loading State -->
                <div v-if="isLoading" class="py-12 text-center">
                    <Icon icon="svg-spinners:ring-resize" class="w-12 h-12 text-[#4DA1A9] mx-auto mb-4" />
                    <p class="text-gray-500 font-medium">جاري تحميل البيانات...</p>
                </div>
                
                <div v-else class="space-y-8">
                    <!-- Drug Info Section -->
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-6">
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
                                        @change="handleInput"
                                        class="w-full h-11 px-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all appearance-none cursor-pointer"
                                        :disabled="isSubmitting"
                                    >
                                        <option value="">كل الفئات</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
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
                                        @focus="showAllDrugsOnFocus"
                                        @blur="hideResults"
                                        placeholder="ابحث عن دواء..."
                                        class="w-full h-11 px-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all disabled:bg-gray-100 disabled:text-gray-400"
                                        :disabled="selectedDrugName.length > 0 || isSubmitting"
                                    />
                                </div>

                                <!-- Search Results -->
                                <div v-if="showResults && uniqueFilteredDrugs.length" class="absolute top-full left-0 right-0 z-30 mt-2 bg-white rounded-xl shadow-xl border border-gray-100 max-h-60 overflow-y-auto">
                                    <ul class="py-2">
                                        <li
                                            v-for="drug in uniqueFilteredDrugs"
                                            :key="drug.id"
                                            @mousedown="selectDrug(drug)"
                                            class="px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors border-b border-gray-50 last:border-0"
                                        >
                                            <div class="flex justify-between items-center">
                                                <span class="font-bold text-[#2E5077]">{{ drug.name || drug.drugName }}</span>
                                                <span v-if="getDrugUnit(drug)" class="text-xs bg-[#EAF3F4] text-[#4DA1A9] px-2 py-1 rounded-lg font-medium">
                                                    {{ getDrugUnit(drug) }}
                                                </span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                            <!-- Quantity Input -->
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-[#2E5077] flex items-center gap-2">
                                    <Icon icon="solar:calculator-minimalistic-bold-duotone" class="w-4 h-4 text-[#4DA1A9]" />
                                    الكمية المطلوبة (<span class="text-[#4DA1A9]">{{ quantityUnit }}</span>)
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    v-model.number="dailyQuantity"
                                    class="w-full h-11 px-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all disabled:bg-gray-100 disabled:text-gray-400"
                                    placeholder="0"
                                    :disabled="!selectedDrugName || isSubmitting"
                                />
                                <p v-if="quantityError" class="text-xs text-red-500 flex items-center gap-1">
                                    <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                    {{ quantityError }}
                                </p>
                            </div>
                            
                            <!-- Add Button -->
                            <button
                                @click="addNewDrug"
                                :disabled="!isCurrentDrugValid || isSubmitting"
                                class="h-11 w-full rounded-xl font-bold flex items-center justify-center gap-2 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
                                :class="(!isCurrentDrugValid || isSubmitting) 
                                    ? 'bg-gray-200 text-gray-400 cursor-not-allowed shadow-none' 
                                    : 'bg-[#4DA1A9] text-white hover:bg-[#3a8c94] hover:-translate-y-0.5'"
                            >
                                <Icon icon="solar:add-circle-bold" class="w-5 h-5" />
                                إضافة للقائمة
                            </button>
                        </div>

                        <!-- Alerts -->
                        <div v-if="!selectedDrugName && (searchTermDrug.length > 0 || selectedCategory) && filteredDrugs.length > 0" class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-blue-600 text-sm flex items-center gap-2">
                            <Icon icon="solar:info-circle-bold" class="w-5 h-5" />
                            الرجاء اختيار دواء من القائمة لتحديد الكمية
                        </div>
                        <div v-else-if="!selectedDrugName && (searchTermDrug.length > 0 || selectedCategory) && filteredDrugs.length === 0" class="p-3 bg-red-50 border border-red-100 rounded-xl text-red-600 text-sm flex items-center gap-2">
                            <Icon icon="solar:danger-circle-bold" class="w-5 h-5" />
                            لا توجد نتائج مطابقة
                        </div>
                    </div>

                    <!-- Added List -->
                    <div v-if="dailyDosageList.length > 0" class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-bold text-[#2E5077] flex items-center gap-2">
                                <Icon icon="solar:list-check-bold-duotone" class="w-5 h-5 text-[#4DA1A9]" />
                                قائمة التوريد
                            </h3>
                            <span class="bg-[#2E5077] text-white text-xs px-2 py-1 rounded-lg">{{ dailyDosageList.length }}</span>
                             <button 
                            @click="clearAllItems"
                            :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors duration-200"
                        >
                            <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-4 h-4" />
                            مسح الكل
                        </button>
                        </div>
                        
                        <ul class="divide-y divide-gray-50 max-h-60 overflow-y-auto">
                            <li v-for="(item, index) in dailyDosageList" :key="index" class="p-4 flex justify-between items-center hover:bg-gray-50/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-blue-600 font-bold text-sm">
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#2E5077]">{{ item.name }}</p>
                                        <p class="text-sm text-gray-500">{{ item.quantity }} {{ item.unit }}</p>
                                    </div>
                                </div>
                                <button 
                                    @click="removeItem(index)"
                                    :disabled="isSubmitting"
                                    class="text-red-400 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition-all"
                                >
                                    <Icon icon="solar:trash-bin-trash-bold-duotone" class="w-5 h-5" />
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div v-else class="py-8 text-center border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
                        <Icon icon="solar:box-minimalistic-bold-duotone" class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500 font-medium">لم يتم إضافة أي أدوية للقائمة بعد</p>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-[#2E5077] flex items-center gap-2">
                            <Icon icon="solar:notebook-bold-duotone" class="w-6 h-6 text-[#4DA1A9]" />
                            ملاحظات الطلب <span class="text-sm font-normal text-gray-400">(اختياري)</span>
                        </h3>
                        <textarea
                            v-model="requestNotes"
                            rows="3"
                            placeholder="أدخل أي ملاحظات خاصة بطلب التوريد..."
                            class="w-full p-4 bg-white border border-gray-200 rounded-xl text-gray-700 focus:border-[#4DA1A9] focus:ring-2 focus:ring-[#4DA1A9]/20 transition-all resize-none"
                            :disabled="isSubmitting"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                    :disabled="isSubmitting"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm || isSubmitting"
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#2E5077]/20 flex items-center gap-2 transition-all duration-200"
                    :class="(!isReadyToConfirm || isSubmitting)
                        ? 'bg-gray-300 cursor-not-allowed shadow-none'
                        : 'bg-[#2E5077] hover:bg-[#1a2f4d] hover:-translate-y-0.5'"
                >
                    <Icon v-if="isSubmitting" icon="svg-spinners:ring-resize" class="w-5 h-5 animate-spin" />
                    <Icon v-else icon="solar:check-read-bold" class="w-5 h-5" />
                    {{ isSubmitting ? 'جاري الإرسال...' : `تأكيد الطلب (${dailyDosageList.length + (isCurrentDrugValid ? 1 : 0)})` }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
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
    allDrugsData: {
        type: Array,
        default: () => []
    },
    drugsData: {
        type: Array,
        default: () => []
    },
    isLoading: {
        type: Boolean,
        default: false
    }
});

const clearAllItems = () => {
    if (dailyDosageList.value.length === 0) {
        emit('show-alert', "⚠️ قائمة التوريد فارغة بالفعل");
        return;
    }
    
    const itemCount = dailyDosageList.value.length;
    dailyDosageList.value = [];
    
    emit('show-alert', `🗑️ تم مسح جميع الأدوية (${itemCount} دواء) من قائمة التوريد`);
};

const emit = defineEmits(['close', 'confirm', 'show-alert']);

// البيانات المحلية
const selectedCategory = ref("");
const searchTermDrug = ref("");
const filteredDrugs = ref([]);
const selectedDrugName = ref("");
const selectedDrugType = ref("");
const dailyQuantity = ref(null);
const showResults = ref(false);
const dailyDosageList = ref([]);
const requestNotes = ref('');
const isSubmitting = ref(false);

// الثوابت
const MAX_PILL_QTY = 1000;
const MAX_LIQUID_QTY = 1000;

// ✅ تحديث دالة البحث لمطابقة البيانات الوهمية
const handleInput = () => {
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;

    if (searchTermDrug.value.length > 1 || selectedCategory.value) {
        filterDrugs();
        showResults.value = true;
    } else {
        filteredDrugs.value = [];
        showResults.value = false;
    }
};

// ✅ دالة تصفية الأدوية
const filterDrugs = () => {
    let drugs = props.allDrugsData || [];

    // البحث حسب الاسم
    if (searchTermDrug.value) {
        const searchTerm = searchTermDrug.value.toLowerCase();
        drugs = drugs.filter(drug => {
            const drugName = drug.name || drug.drugName || '';
            const drugCode = drug.drugCode || '';
            return drugName.toLowerCase().includes(searchTerm) || 
                   drugCode.toLowerCase().includes(searchTerm);
        });
    }

    // البحث حسب الفئة
    if (selectedCategory.value) {
        drugs = drugs.filter(drug => drug.categoryId == selectedCategory.value);
    }

    filteredDrugs.value = drugs;
};

// ✅ الحصول على وحدة الدواء
const getDrugUnit = (drug) => {
    if (drug.type === 'Tablet' || drug.type === 'Capsule') return 'حبة/قرص';
    if (drug.type === 'Liquid' || drug.type === 'Syrup') return 'مل';
    if (drug.type === 'Injection') return 'أمبول';
    if (drug.type === 'Ointment') return 'جرام';
    return 'وحدة';
};

const quantityUnit = computed(() => {
    if (selectedDrugType.value === "Tablet" || selectedDrugType.value === "Capsule") return "حبة/قرص";
    if (selectedDrugType.value === "Liquid" || selectedDrugType.value === "Syrup") return "مل";
    if (selectedDrugType.value === "Injection") return "أمبول";
    if (selectedDrugType.value === "Ointment") return "جرام";
    return "وحدة";
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "" || quantity === 0) return "الرجاء إدخال كمية التوريد";

    const numericQuantity = Number(quantity);
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر";
    }

    if (selectedDrugType.value === "Tablet" || selectedDrugType.value === "Capsule") {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية ${MAX_PILL_QTY} حبة/قرص`;
        }
        if (!Number.isInteger(numericQuantity)) {
            return "يجب أن يكون عدد الحبات رقماً صحيحاً";
        }
    } else if (selectedDrugType.value === "Liquid" || selectedDrugType.value === "Syrup") {
        if (numericQuantity > MAX_LIQUID_QTY) {
            return `لا يمكن أن تتجاوز الكمية ${MAX_LIQUID_QTY} مل`;
        }
    }

    return null;
});

const isCurrentDrugValid = computed(() => {
    const quantity = dailyQuantity.value;
    const isQuantityValid = quantity !== null && quantity > 0;
    const noQuantityError = quantityError.value === null;

    return (
        selectedDrugName.value.length > 0 &&
        selectedDrugType.value.length > 0 &&
        isQuantityValid &&
        noQuantityError
    );
});

const isReadyToConfirm = computed(() => {
    return dailyDosageList.value.length > 0 || isCurrentDrugValid.value;
});

const uniqueFilteredDrugs = computed(() => {
    const uniqueNames = new Set();
    return filteredDrugs.value.filter(drug => {
        const drugName = drug.name || drug.drugName || '';
        if (uniqueNames.has(drugName)) return false;
        uniqueNames.add(drugName);
        return true;
    });
});

// الوظائف
const clearForm = () => {
    selectedCategory.value = "";
    searchTermDrug.value = "";
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;
    dailyDosageList.value = [];
    filteredDrugs.value = [];
    requestNotes.value = '';
    isSubmitting.value = false;
};

const getDrugType = (drugName) => {
    const drugInfo = props.allDrugsData.find(d => {
        const dName = d.name || d.drugName || '';
        return dName.toLowerCase() === drugName.toLowerCase() ||
               drugName.toLowerCase().includes(dName.toLowerCase());
    });
    return drugInfo ? (drugInfo.type || 'Tablet') : 'Tablet';
};

const selectDrug = (drug) => {
    const drugName = drug.name || drug.drugName || '';
    searchTermDrug.value = drugName;
    selectedDrugName.value = drugName;

    const fullDrugData = props.allDrugsData.find((d) => {
        const dName = d.name || d.drugName || '';
        return dName.toLowerCase() === drugName.toLowerCase();
    });
    
    const newDrugType = fullDrugData ? (fullDrugData.type || 'Tablet') : 'Tablet';
    selectedDrugType.value = newDrugType;

    dailyQuantity.value = null;
    showResults.value = false;
};

const hideResults = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

const showAllDrugsOnFocus = () => {
    if (!searchTermDrug.value && !selectedCategory.value) {
        // عرض جميع الأدوية عند التركيز على حقل البحث
        filteredDrugs.value = props.allDrugsData || [];
        showResults.value = true;
    } else {
        handleInput();
    }
};

const addNewDrug = () => {
    if (isCurrentDrugValid.value) {
        const drugInfo = props.allDrugsData.find(d => {
            const dName = d.name || d.drugName || '';
            return dName.toLowerCase() === selectedDrugName.value.toLowerCase();
        });
        
        if (!drugInfo || !drugInfo.id) {
            emit('show-alert', `❌ فشل: لم يتم العثور على معرف الدواء ${selectedDrugName.value}`);
            return;
        }
        
        dailyDosageList.value.push({
            drugId: drugInfo.id,
            id: drugInfo.id,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
        });

        emit('show-alert', `✅ تم إضافة الدواء **${selectedDrugName.value}** إلى قائمة التوريد`);

        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
        filteredDrugs.value = [];
    } else {
        const errorMessage = quantityError.value || "الرجاء تحديد دواء وإدخال كمية صحيحة";
        emit('show-alert', `❌ فشل الإضافة: ${errorMessage}`);
    }
};

const removeItem = (index) => {
    dailyDosageList.value.splice(index, 1);
    emit('show-alert', "🗑️ تم حذف الدواء من القائمة");
};

const confirmAddition = () => {
    if (isCurrentDrugValid.value) {
        const drugInfo = props.allDrugsData.find(d => {
            const dName = d.name || d.drugName || '';
            return dName.toLowerCase() === selectedDrugName.value.toLowerCase();
        });
        
        if (drugInfo && drugInfo.id) {
            dailyDosageList.value.push({
                drugId: drugInfo.id,
                id: drugInfo.id,
                name: selectedDrugName.value,
                quantity: dailyQuantity.value,
                unit: quantityUnit.value,
                type: selectedDrugType.value,
            });
        }
        
        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    }

    if (dailyDosageList.value.length === 0) {
        emit('show-alert', "⚠️ لا يمكنك التأكيد دون إضافة دواء واحد على الأقل");
        return;
    }

    isSubmitting.value = true;
    
    try {
        console.log('Confirming with items:', dailyDosageList.value); // للتصحيح
        
        const confirmationData = {
            items: dailyDosageList.value,
            notes: requestNotes.value.trim(),
            totalItems: dailyDosageList.value.length,
            timestamp: new Date().toISOString()
        };
        
        emit('confirm', confirmationData);
        
        setTimeout(() => {
            clearForm();
        }, 500);
        
    } catch (error) {
        console.error('Error submitting supply request:', error);
        emit('show-alert', `❌ فشل في إرسال طلب التوريد: ${error.message}`);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    if (!isSubmitting.value) {
        clearForm();
        emit('close');
    }
};

watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        clearForm();
        
        // عرض جميع الأدوية عند فتح النموذج
        filteredDrugs.value = props.allDrugsData || [];
        
        // التحقق من الأدوية التي تحتاج توريد
        if (props.drugsData && props.drugsData.length > 0) {
            const drugsNeedingSupply = [];
            
            props.drugsData.forEach((drug) => {
                const neededSupply = (drug.neededQuantity || 0) - (drug.quantity || 0);
                
                if (neededSupply > 0) {
                    const drugInfo = props.allDrugsData.find(d => 
                        (d.name === drug.drugName) || 
                        (d.drugName === drug.drugName) ||
                        (d.id === drug.drugCode) ||
                        (drug.drugName && drug.drugName.includes((d.name || d.drugName || '').split(' ')[0]))
                    );
                    
                    // التأكد من أن drugInfo يحتوي على id
                    if (!drugInfo || !drugInfo.id) {
                        console.warn(`Drug ID not found for: ${drug.drugName}`);
                        return; // تخطي هذا الدواء
                    }
                    
                    const drugType = drugInfo.type || 'Tablet';
                    const unit = getDrugUnit({ type: drugType });
                    
                    drugsNeedingSupply.push({
                        drugId: drugInfo.id, // استخدام ID من allDrugsData وليس من inventories
                        id: drugInfo.id,
                        drugCode: drug.drugCode,
                        name: drug.drugName,
                        currentQuantity: drug.quantity,
                        neededQuantity: drug.neededQuantity,
                        quantity: neededSupply,
                        unit: unit,
                        type: drugType,
                        expiryDate: drug.expiryDate
                    });
                }
            });
            
            if (drugsNeedingSupply.length > 0) {
                // التأكد من وجود drugId لكل عنصر
                const validDrugs = drugsNeedingSupply.filter(drug => drug.drugId);
                
                if (validDrugs.length > 0) {
                    dailyDosageList.value = validDrugs;
                    
                    requestNotes.value = `توريد تلقائي للأدوية الناقصة - ${new Date().toLocaleDateString('ar-EG')}`;
                    
                    const totalItems = validDrugs.length;
                    const totalQuantity = validDrugs.reduce((sum, drug) => sum + drug.quantity, 0);
                    
                    emit('show-alert', 
                        `📋 تم إدراج ${totalItems} دواء ناقص تلقائياً (إجمالي ${totalQuantity} وحدة)`
                    );
                } else {
                    emit('show-alert', "⚠️ لم يتم العثور على أدوية صالحة للتوريد");
                }
            } else {
                emit('show-alert', "✅ جميع الأدوية متوفرة بالكميات المطلوبة حالياً");
            }
        }
    }
});

// التهيئة الأولية
if (props.isOpen) {
    filteredDrugs.value = props.allDrugsData || [];
}
</script>