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
                    طلب توريد أدوية - القسم
                </h2>
                <button @click="closeModal" class="text-white/70 hover:text-white hover:bg-white/10 p-2 rounded-full transition-all duration-300 relative z-10">
                    <Icon icon="mingcute:close-fill" class="w-6 h-6" />
                </button>
            </div>

            <div class="p-8 space-y-8">
                
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
                                    :disabled="selectedDrugName.length > 0"
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
                                            <span class="font-bold text-[#2E5077]">{{ drug.name }}</span>
                                            <span v-if="drug.mostCommonDosage" class="text-xs bg-[#EAF3F4] text-[#4DA1A9] px-2 py-1 rounded-lg font-medium">
                                                {{ drug.mostCommonDosage }}
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
                                :disabled="!selectedDrugName"
                            />
                            <p v-if="quantityError" class="text-xs text-red-500 flex items-center gap-1">
                                <Icon icon="solar:danger-circle-bold" class="w-3 h-3" />
                                {{ quantityError }}
                            </p>
                        </div>
                        
                        <!-- Add Button -->
                        <button
                            @click="addNewDrug"
                            :disabled="!isCurrentDrugValid"
                            class="h-11 w-full rounded-xl font-bold flex items-center justify-center gap-2 transition-all duration-200 shadow-lg shadow-[#4DA1A9]/20"
                            :class="(!isCurrentDrugValid) 
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
                    ></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-8 py-5 flex justify-end gap-3 border-t border-gray-100 sticky bottom-0">
                <button 
                    @click="closeModal" 
                    class="px-6 py-2.5 rounded-xl text-[#2E5077] font-medium hover:bg-gray-200 transition-colors duration-200"
                >
                    إلغاء
                </button>
                <button
                    @click="confirmAddition"
                    :disabled="!isReadyToConfirm"
                    class="px-6 py-2.5 rounded-xl text-white font-medium shadow-lg shadow-[#2E5077]/20 flex items-center gap-2 transition-all duration-200"
                    :class="(!isReadyToConfirm)
                        ? 'bg-gray-300 cursor-not-allowed shadow-none'
                        : 'bg-[#2E5077] hover:bg-[#1a2f4d] hover:-translate-y-0.5'"
                >
                    <Icon icon="solar:check-read-bold" class="w-5 h-5" />
                    تأكيد الطلب ({{ dailyDosageList.length + (isCurrentDrugValid ? 1 : 0) }})
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
    drugsData: { // المخزون الحالي
        type: Array,
        default: () => []
    }
});

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

// الثوابت
const MAX_PILL_QTY = 15;
const MAX_LIQUID_QTY = 30;

// Computed Properties
const uniqueFilteredDrugs = computed(() => {
    const uniqueNames = new Set();
    const uniqueDrugs = [];

    filteredDrugs.value.forEach((drug) => {
        if (!uniqueNames.has(drug.name)) {
            uniqueNames.add(drug.name);
            const commonDosage = props.allDrugsData.find(
                (d) => d.name === drug.name
            )?.dosage;
            uniqueDrugs.push({ ...drug, mostCommonDosage: commonDosage });
        }
    });
    return uniqueDrugs.slice(0, 10);
});

const quantityUnit = computed(() => {
    if (selectedDrugType.value === "Tablet") return "حبة/قرص";
    if (selectedDrugType.value === "Liquid") return "مل";
    return "وحدة";
});

const quantityInputId = computed(() => {
    return selectedDrugType.value === "Tablet"
        ? "pill-quantity-input"
        : "liquid-quantity-input";
});

const quantityError = computed(() => {
    const quantity = dailyQuantity.value;
    if (quantity === null || quantity === "") return null;

    const numericQuantity = Number(quantity);
    if (isNaN(numericQuantity)) return "الكمية يجب أن تكون رقماً.";

    if (numericQuantity <= 0) {
        return "يجب أن تكون الكمية أكبر من الصفر.";
    }

    if (selectedDrugType.value === "Tablet") {
        if (numericQuantity > MAX_PILL_QTY) {
            return `لا يمكن أن تتجاوز الكمية اليومية ${MAX_PILL_QTY} حبة/قرص.`;
        }
        if (!Number.isInteger(numericQuantity)) {
            return "يجب أن يكون عدد الحبات رقماً صحيحاً (غير عشري).";
        }
    } else if (selectedDrugType.value === "Liquid") {
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
};

const getDrugType = (drugName) => {
    const drugInfo = props.allDrugsData.find(d => d.name.toLowerCase() === drugName.toLowerCase());
    return drugInfo ? drugInfo.type : 'Tablet';
};

const fetchDrugsData = () => {
    let results = props.allDrugsData.filter((drug) => {
        const categoryMatch =
            !selectedCategory.value ||
            drug.categoryId === selectedCategory.value;
        const searchMatch =
            !searchTermDrug.value ||
            drug.name
                .toLowerCase()
                .includes(searchTermDrug.value.toLowerCase());
        return categoryMatch && searchMatch;
    });

    filteredDrugs.value = results;
};

let debounceTimer;
const handleInput = () => {
    selectedDrugName.value = "";
    selectedDrugType.value = "";
    dailyQuantity.value = null;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (searchTermDrug.value.length > 1 || selectedCategory.value) {
            fetchDrugsData();
            showResults.value = true;
        } else {
            filteredDrugs.value = [];
            showResults.value = false;
        }
    }, 300);
};

const selectDrug = (drug) => {
    searchTermDrug.value = drug.name;
    selectedDrugName.value = drug.name;

    const fullDrugData = props.allDrugsData.find((d) => d.name === drug.name);
    const newDrugType = fullDrugData ? fullDrugData.type : "";
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
        filteredDrugs.value = props.allDrugsData;
        showResults.value = true;
    } else {
        handleInput();
    }
};

const addNewDrug = () => {
    if (isCurrentDrugValid.value) {
        // البحث عن معلومات الدواء الكاملة للحصول على drugId
        const drugInfo = props.allDrugsData.find(d => 
            d.name === selectedDrugName.value || 
            d.name?.toLowerCase() === selectedDrugName.value.toLowerCase()
        );
        
        dailyDosageList.value.push({
            drugId: drugInfo?.id || null,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
            time: new Date().toLocaleTimeString("ar-EG", {
                hour: "2-digit",
                minute: "2-digit",
            }),
        });

        emit('show-alert', `✅ تم إضافة الدواء **${selectedDrugName.value}** إلى قائمة التوريد.`);

        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    } else {
        const errorMessage =
            quantityError.value ||
            "الرجاء تحديد دواء وإدخال كمية توريد صحيحة أكبر من الصفر.";
        emit('show-alert', `❌ فشل الإضافة: ${errorMessage}`);
    }
};

const removeItem = (index) => {
    dailyDosageList.value.splice(index, 1);
    emit('show-alert', "🗑️ تم حذف الدواء من القائمة بنجاح.");
};

const confirmAddition = () => {
    if (isCurrentDrugValid.value) {
        // البحث عن معلومات الدواء الكاملة للحصول على drugId
        const drugInfo = props.allDrugsData.find(d => 
            d.name === selectedDrugName.value || 
            d.name?.toLowerCase() === selectedDrugName.value.toLowerCase()
        );
        
        dailyDosageList.value.push({
            drugId: drugInfo?.id || null,
            name: selectedDrugName.value,
            quantity: dailyQuantity.value,
            unit: quantityUnit.value,
            type: selectedDrugType.value,
            time: new Date().toLocaleTimeString("ar-EG", {
                hour: "2-digit",
                minute: "2-digit",
            }),
        });
        searchTermDrug.value = "";
        selectedCategory.value = "";
        selectedDrugName.value = "";
        selectedDrugType.value = "";
        dailyQuantity.value = null;
    }

    if (dailyDosageList.value.length === 0) {
        emit('show-alert', "⚠️ لا يمكنك التأكيد دون إضافة دواء واحد على الأقل.");
    } else {
        const confirmationData = {
            items: dailyDosageList.value,
            notes: requestNotes.value.trim()
        };
        emit('confirm', confirmationData);
        closeModal();
    }
};

const closeModal = () => {
    clearForm();
    emit('close');
};

// عند فتح النموذج (للتعبئة التلقائية للأدوية الناقصة)
watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        clearForm();
        
        const TARGET_QUANTITY = 100;
        const REORDER_THRESHOLD = 20;

        const drugsToReorder = props.drugsData.filter(
            (drug) => drug.quantity <= REORDER_THRESHOLD
        );

        if (drugsToReorder.length > 0) {
            const reorderList = drugsToReorder.map((drug) => {
                const quantityNeeded = TARGET_QUANTITY - drug.quantity;
                const drugType = getDrugType(drug.drugName);
                const unit = drugType === 'Liquid' ? 'مل' : 'حبة/قرص';
                
                // البحث عن معلومات الدواء الكاملة للحصول على drugId
                const drugInfo = props.allDrugsData.find(d => 
                    d.name === drug.drugName || 
                    d.name?.toLowerCase() === drug.drugName?.toLowerCase()
                );

                return {
                    drugId: drugInfo?.id || drug.id || null,
                    name: drug.drugName,
                    quantity: quantityNeeded > 0 ? quantityNeeded : 0,
                    unit: unit,
                    type: drugType,
                    time: new Date().toLocaleTimeString("ar-EG", {
                        hour: "2-digit",
                        minute: "2-digit",
                    }),
                };
            });

            dailyDosageList.value = reorderList;
            emit('show-alert', `💡 تم إدراج ${reorderList.length} دواء ناقص في القائمة تلقائياً.`);
        }
    }
});

// التهيئة الأولية
fetchDrugsData();
</script>